<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Http\Request;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebauthnService
{
    private SerializerInterface $serializer;

    private AttestationStatementSupportManager $attestationManager;

    public function __construct()
    {
        $this->attestationManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport,
        ]);
        $this->serializer = (new WebauthnSerializerFactory($this->attestationManager))->create();
    }

    /** @return array{publicKey: array<string, mixed>, ceremony: array<string, string>} */
    public function registrationOptions(User $user, Request $request): array
    {
        $context = $this->requestContext($request);
        $excludeCredentials = $user->webauthnCredentials
            ->map(fn (WebauthnCredential $credential) => $this->credentialRecord($credential)->getPublicKeyCredentialDescriptor())
            ->all();

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create(
                (string) config('passkeys.name', 'WIS School System'),
                $context['rp_id'],
            ),
            PublicKeyCredentialUserEntity::create(
                $user->username ?: $user->email,
                $this->userHandle($user),
                $user->name ?: ($user->username ?: $user->email),
            ),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials,
            $this->timeout(),
        );

        return $this->optionsResponse($options, $context);
    }

    /** @return array{publicKey: array<string, mixed>, ceremony: array<string, string>} */
    public function authenticationOptions(Request $request): array
    {
        $context = $this->requestContext($request);
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $context['rp_id'],
            [],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            $this->timeout(),
        );

        return $this->optionsResponse($options, $context);
    }

    /** @param array<string, mixed> $credentialData @param array<string, mixed> $ceremony */
    public function register(User $user, array $credentialData, array $ceremony, string $name): WebauthnCredential
    {
        $credential = $this->publicKeyCredential($credentialData);
        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new \InvalidArgumentException('An attestation response is required.');
        }

        $options = $this->serializer->deserialize(
            $ceremony['options'],
            PublicKeyCredentialCreationOptions::class,
            'json',
        );
        $factory = $this->ceremonyFactory($ceremony);
        $record = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony())
            ->check($credential->response, $options, $ceremony['rp_id']);

        if (! hash_equals($this->userHandle($user), $record->userHandle)) {
            throw new \RuntimeException('The passkey belongs to a different user.');
        }

        $encodedId = Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId);

        return $user->webauthnCredentials()->create([
            'name' => trim($name),
            'credential_id_hash' => hash('sha256', $record->publicKeyCredentialId),
            'credential_id' => $encodedId,
            'credential_record' => $this->serializer->serialize($record, 'json'),
        ]);
    }

    /** @param array<string, mixed> $credentialData @param array<string, mixed> $ceremony */
    public function authenticate(array $credentialData, array $ceremony): WebauthnCredential
    {
        $credential = $this->publicKeyCredential($credentialData);
        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new \InvalidArgumentException('An assertion response is required.');
        }

        $stored = WebauthnCredential::query()
            ->with('user')
            ->where('credential_id_hash', hash('sha256', $credential->rawId))
            ->firstOrFail();
        if (! hash_equals(Base64UrlSafe::decodeNoPadding($stored->credential_id), $credential->rawId)) {
            throw new \RuntimeException('The passkey credential ID is invalid.');
        }
        if (! $stored->user || (int) $stored->user->status !== 1) {
            throw new \RuntimeException('This account is inactive.');
        }

        $options = $this->serializer->deserialize(
            $ceremony['options'],
            PublicKeyCredentialRequestOptions::class,
            'json',
        );
        $record = $this->credentialRecord($stored);
        $updatedRecord = AuthenticatorAssertionResponseValidator::create(
            $this->ceremonyFactory($ceremony)->requestCeremony(),
        )->check($record, $credential->response, $options, $ceremony['rp_id'], null);

        if (! hash_equals($this->userHandle($stored->user), $updatedRecord->userHandle)) {
            throw new \RuntimeException('The passkey user handle is invalid.');
        }

        $stored->forceFill([
            'credential_record' => $this->serializer->serialize($updatedRecord, 'json'),
            'last_used_at' => now(),
        ])->save();

        return $stored;
    }

    /** @param PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options @param array{rp_id: string, origin: string} $context */
    private function optionsResponse(object $options, array $context): array
    {
        $serialized = $this->serializer->serialize($options, 'json');

        return [
            'publicKey' => json_decode($serialized, true, flags: JSON_THROW_ON_ERROR),
            'ceremony' => [
                'options' => $serialized,
                'rp_id' => $context['rp_id'],
                'origin' => $context['origin'],
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function publicKeyCredential(array $data): PublicKeyCredential
    {
        $credential = $this->serializer->deserialize(
            json_encode($data, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json',
        );
        if (! $credential instanceof PublicKeyCredential) {
            throw new \InvalidArgumentException('Invalid passkey response.');
        }

        return $credential;
    }

    private function credentialRecord(WebauthnCredential $credential): CredentialRecord
    {
        $record = $this->serializer->deserialize($credential->credential_record, CredentialRecord::class, 'json');
        if (! $record instanceof CredentialRecord) {
            throw new \RuntimeException('The stored passkey is invalid.');
        }

        return $record;
    }

    /** @param array<string, mixed> $ceremony */
    private function ceremonyFactory(array $ceremony): CeremonyStepManagerFactory
    {
        foreach (['options', 'rp_id', 'origin'] as $key) {
            if (! isset($ceremony[$key]) || $ceremony[$key] === '') {
                throw new \InvalidArgumentException('The passkey request expired.');
            }
        }
        $factory = new CeremonyStepManagerFactory;
        $factory->setAttestationStatementSupportManager($this->attestationManager);
        $factory->setAllowedOrigins([$ceremony['origin']]);

        return $factory;
    }

    /** @return array{rp_id: string, origin: string} */
    private function requestContext(Request $request): array
    {
        $rpId = trim((string) config('passkeys.rp_id')) ?: $request->getHost();
        $origin = rtrim(trim((string) config('passkeys.origin')) ?: $request->getSchemeAndHttpHost(), '/');

        return ['rp_id' => $rpId, 'origin' => $origin];
    }

    private function userHandle(User $user): string
    {
        return hash_hmac('sha256', 'webauthn-user:'.$user->getKey(), (string) config('app.key'), true);
    }

    private function timeout(): int
    {
        return max(15000, min(120000, (int) config('passkeys.timeout', 60000)));
    }
}
