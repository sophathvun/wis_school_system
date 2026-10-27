const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || "";

const decodeBase64Url = (value) => {
    const base64 = value.replace(/-/g, "+").replace(/_/g, "/");
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, "=");
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
};

const encodeBase64Url = (value) => {
    const bytes = new Uint8Array(value);
    let binary = "";
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });
    return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
};

const parseCreationOptions = (options) => {
    if (typeof PublicKeyCredential.parseCreationOptionsFromJSON === "function") {
        return PublicKeyCredential.parseCreationOptionsFromJSON(options);
    }
    return {
        ...options,
        challenge: decodeBase64Url(options.challenge),
        user: { ...options.user, id: decodeBase64Url(options.user.id) },
        excludeCredentials: (options.excludeCredentials || []).map((credential) => ({
            ...credential,
            id: decodeBase64Url(credential.id),
        })),
    };
};

const parseRequestOptions = (options) => {
    if (typeof PublicKeyCredential.parseRequestOptionsFromJSON === "function") {
        return PublicKeyCredential.parseRequestOptionsFromJSON(options);
    }
    return {
        ...options,
        challenge: decodeBase64Url(options.challenge),
        allowCredentials: (options.allowCredentials || []).map((credential) => ({
            ...credential,
            id: decodeBase64Url(credential.id),
        })),
    };
};

const credentialToJSON = (credential) => {
    if (typeof credential.toJSON === "function") return credential.toJSON();
    const response = credential.response;
    const result = {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: encodeBase64Url(response.clientDataJSON),
        },
    };
    if (response.attestationObject) {
        result.response.attestationObject = encodeBase64Url(response.attestationObject);
        result.response.transports = response.getTransports?.() || [];
    } else {
        result.response.authenticatorData = encodeBase64Url(response.authenticatorData);
        result.response.signature = encodeBase64Url(response.signature);
        result.response.userHandle = response.userHandle
            ? encodeBase64Url(response.userHandle)
            : null;
    }
    return result;
};

export const supportsPasskeys = () =>
    window.isSecureContext &&
    "PublicKeyCredential" in window &&
    Boolean(navigator.credentials);

export const createPasskey = async (options) => {
    const credential = await navigator.credentials.create({
        publicKey: parseCreationOptions(options),
    });
    if (!credential) throw new Error("The browser did not return a passkey.");
    return credentialToJSON(credential);
};

export const getPasskey = async (options) => {
    const credential = await navigator.credentials.get({
        publicKey: parseRequestOptions(options),
    });
    if (!credential) throw new Error("The browser did not return a passkey.");
    return credentialToJSON(credential);
};

export const passkeyRequest = async (url, body = {}) => {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validationMessage = Object.values(result.errors || {}).flat()[0];
        throw new Error(validationMessage || result.message || "The passkey request failed.");
    }
    return result;
};

export const deletePasskey = async (url) => {
    const response = await fetch(url, {
        method: "DELETE",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || "The passkey could not be removed.");
    return result;
};
