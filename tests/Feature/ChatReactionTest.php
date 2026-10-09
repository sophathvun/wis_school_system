<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Schema::create('users', function (Blueprint $table) {
        $table->id(); $table->string('name'); $table->integer('department_id')->nullable();
        $table->string('photo_path')->nullable(); $table->timestamp('last_seen_at')->nullable(); $table->timestamps();
    });
    Schema::create('tb_department', function (Blueprint $table) { $table->id(); $table->string('name'); $table->softDeletes(); });
    foreach ([
        '2026_08_04_000026_create_chat_tables.php',
        '2026_08_09_000001_extend_chat_for_voice_calls_and_voice_messages.php',
        '2026_09_18_000001_add_group_management_to_chat.php',
        '2026_09_18_000002_create_chat_message_deletions.php',
        '2026_10_09_000016_create_chat_message_reactions.php',
        '2026_10_09_000017_add_chat_message_replies.php',
    ] as $migration) (require database_path('migrations/'.$migration))->up();
    DB::table('users')->insert([
        ['id'=>1,'name'=>'Sender'], ['id'=>2,'name'=>'Reader'], ['id'=>3,'name'=>'Other member'], ['id'=>4,'name'=>'Outsider'],
    ]);
    $this->conversation=ChatConversation::create(['type'=>'group','title'=>'Testing','created_by'=>1]);
    $this->conversation->users()->attach([1,2,3]);
    $this->message=$this->conversation->messages()->create(['user_id'=>1,'message'=>'Please review this.']);
    $this->url=route('chat.messages.reaction',$this->message);
    $this->actingAs(User::find(2));
});

it('allows all supported reactions on text attachments voice and call messages', function ($emoji, $type) {
    $this->message->update(['message_type'=>$type]);
    $this->putJson($this->url,['emoji'=>$emoji])->assertOk()->assertJsonPath('reactions.0.emoji',$emoji)
        ->assertJsonPath('reactions.0.count',1)->assertJsonPath('reactions.0.reacted',true)
        ->assertJsonPath('reactions.0.users.0.name','Reader');
    expect(ChatMessageReaction::firstOrFail()->user_id)->toBe(2)
        ->and($this->message->fresh()->message)->toBe('Please review this.');
})->with(array_map(fn ($emoji, $index)=>[$emoji,['text','text','image','file','voice','call'][$index]],ChatMessageReaction::EMOJIS,array_keys(ChatMessageReaction::EMOJIS)));

it('keeps one reaction per member and makes setting or removing it idempotent', function () {
    $heart=ChatMessageReaction::EMOJIS[0]; $thanks=ChatMessageReaction::EMOJIS[1];
    $this->putJson($this->url,['emoji'=>$heart])->assertOk();
    $this->putJson($this->url,['emoji'=>$heart])->assertOk()->assertJsonPath('reactions.0.count',1);
    $this->actingAs(User::find(3))->putJson($this->url,['emoji'=>$heart])->assertOk()->assertJsonPath('reactions.0.count',2);
    $this->actingAs(User::find(2))->putJson($this->url,['emoji'=>$thanks])->assertOk();
    expect(ChatMessageReaction::count())->toBe(2)
        ->and(ChatMessageReaction::where('user_id',2)->value('emoji'))->toBe($thanks);
    $this->deleteJson($this->url)->assertOk()->assertJsonPath('reactions.0.emoji',$heart)->assertJsonPath('reactions.0.reacted',false);
    $this->deleteJson($this->url)->assertOk()->assertJsonPath('reactions.0.count',1);
    expect(ChatMessageReaction::firstOrFail()->user_id)->toBe(3);
});

it('includes counts and the viewers reaction when messages are refreshed by different members', function () {
    $heart=ChatMessageReaction::EMOJIS[0];
    $this->putJson($this->url,['emoji'=>$heart])->assertOk();
    $this->actingAs(User::find(3))->putJson($this->url,['emoji'=>$heart])->assertOk();
    $this->actingAs(User::find(1))->getJson(route('chat.messages',$this->conversation))->assertOk()
        ->assertJsonPath('messages.0.can_react',true)->assertJsonPath('messages.0.reactions.0.count',2)
        ->assertJsonPath('messages.0.reactions.0.reacted',false);
    $this->actingAs(User::find(2))->getJson(route('chat.messages',$this->conversation))->assertOk()
        ->assertJsonPath('messages.0.reactions.0.reacted',true)
        ->assertJsonCount(2,'messages.0.reactions.0.users');
});

it('allows reacting in direct chats and to your own message', function () {
    $this->conversation->update(['type'=>'direct']);
    $this->conversation->users()->detach(3);
    $this->actingAs(User::find(1))->putJson($this->url,['emoji'=>ChatMessageReaction::EMOJIS[2]])->assertOk();
    expect(ChatMessageReaction::firstOrFail()->user_id)->toBe(1);
});

it('returns reaction controls immediately for newly sent messages without sending a reaction notification', function () {
    $this->mock(\App\Services\WebPushService::class, function ($mock) {
        $mock->shouldReceive('sendToUsers')->once();
    });
    $response=$this->postJson(route('chat.messages.send',$this->conversation),['message'=>'New message'])->assertOk()
        ->assertJsonPath('can_react',true)->assertJsonPath('reactions',[]);
    $this->putJson(route('chat.messages.reaction',$response->json('id')),['emoji'=>ChatMessageReaction::EMOJIS[1]])->assertOk();
    expect(ChatMessage::count())->toBe(2);
});

it('rejects unsupported empty and non-string reactions without changing an existing choice', function ($emoji) {
    $heart=ChatMessageReaction::EMOJIS[0];
    $this->putJson($this->url,['emoji'=>$heart])->assertOk();
    $this->putJson($this->url,['emoji'=>$emoji])->assertUnprocessable()->assertJsonValidationErrors('emoji');
    expect(ChatMessageReaction::firstOrFail()->emoji)->toBe($heart);
})->with(['invalid','<script>alert(1)</script>','',null,123]);

it('requires conversation membership for both setting and removing reactions', function () {
    $this->putJson($this->url,['emoji'=>ChatMessageReaction::EMOJIS[0]])->assertOk();
    $this->actingAs(User::find(4))->putJson($this->url,['emoji'=>ChatMessageReaction::EMOJIS[1]])->assertForbidden();
    $this->deleteJson($this->url)->assertForbidden();
    $this->conversation->users()->detach(2);
    $this->actingAs(User::find(2))->deleteJson($this->url)->assertForbidden();
    expect(ChatMessageReaction::count())->toBe(1);
});

it('does not allow reactions on deleted hidden or deleted-conversation messages', function ($mode) {
    if ($mode==='message') $this->message->delete();
    elseif ($mode==='conversation') $this->conversation->delete();
    else $this->message->hiddenByUsers()->attach(2);
    $this->putJson($this->url,['emoji'=>ChatMessageReaction::EMOJIS[0]])->assertNotFound();
    $this->deleteJson($this->url)->assertNotFound();
    expect(ChatMessageReaction::count())->toBe(0);
})->with(['message','conversation','hidden']);

it('requires sign in for reactions', function () {
    auth()->forgetGuards();
    $this->putJson($this->url,['emoji'=>ChatMessageReaction::EMOJIS[0]])->assertUnauthorized();
    $this->deleteJson($this->url)->assertUnauthorized();
});
