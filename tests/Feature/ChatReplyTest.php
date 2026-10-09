<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Storage::fake('public');
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
    DB::table('users')->insert([['id'=>1,'name'=>'Sender'],['id'=>2,'name'=>'Reader'],['id'=>3,'name'=>'Outsider']]);
    $this->conversation=ChatConversation::create(['type'=>'group','title'=>'Testing','created_by'=>1]);
    $this->conversation->users()->attach([1,2]);
    $this->message=$this->conversation->messages()->create(['user_id'=>1,'message'=>'Please review this.']);
    $this->actingAs(User::find(2));
    $this->mock(WebPushService::class, fn ($mock)=>$mock->shouldReceive('sendToUsers')->andReturnNull());
});

it('links a reply to the selected message in direct and group chats', function ($type) {
    $this->conversation->update(['type'=>$type]);
    $response=$this->postJson(route('chat.messages.send',$this->conversation),[
        'message'=>'I will check it.','reply_to_message_id'=>$this->message->id,
    ])->assertOk()->assertJsonPath('reply_to.id',$this->message->id)->assertJsonPath('reply_to.user_name','Sender')
        ->assertJsonPath('reply_to.message','Please review this.')->assertJsonPath('can_reply',true);
    expect(ChatMessage::findOrFail($response->json('id'))->reply_to_message_id)->toBe($this->message->id);
    $this->actingAs(User::find(1))->getJson(route('chat.messages',$this->conversation))->assertOk()
        ->assertJsonPath('messages.1.reply_to.id',$this->message->id)->assertJsonPath('messages.1.message','I will check it.');
})->with(['direct','group']);

it('supports replying to your own message and replying to a reply', function () {
    $this->actingAs(User::find(1));
    $response=$this->postJson(route('chat.messages.send',$this->conversation),['message'=>'Clarification','reply_to_message_id'=>$this->message->id])->assertOk();
    $id=$response->json('id');
    $this->postJson(route('chat.messages.send',$this->conversation),['message'=>'Another detail','reply_to_message_id'=>$id])->assertOk()
        ->assertJsonPath('reply_to.id',$id)->assertJsonPath('reply_to.message','Clarification');
});

it('keeps regular messages without a reply unchanged', function () {
    $this->postJson(route('chat.messages.send',$this->conversation),['message'=>'Hello'])->assertOk()->assertJsonPath('reply_to',null);
    expect(ChatMessage::latest('id')->firstOrFail()->reply_to_message_id)->toBeNull();
});

it('supports attachment and voice replies', function ($type) {
    $data=['reply_to_message_id'=>$this->message->id];
    if ($type==='voice') $data['audio']=UploadedFile::fake()->create('voice.webm',12,'audio/webm');
    else $data['attachment']=UploadedFile::fake()->create('document.pdf',12,'application/pdf');
    $route=$type==='voice'?'chat.voice.send':'chat.messages.send';
    $this->postJson(route($route,$this->conversation),$data)->assertOk()->assertJsonPath('reply_to.id',$this->message->id)
        ->assertJsonPath('message_type',$type==='voice'?'voice':'file');
    expect(ChatMessage::latest('id')->firstOrFail()->reply_to_message_id)->toBe($this->message->id);
})->with(['attachment','voice']);

it('does not accept an unavailable or invalid reply target before storing uploads', function ($invalid, $voice) {
    $id=$this->message->id;
    if ($invalid==='foreign') {
        $other=ChatConversation::create(['type'=>'direct','created_by'=>1]); $other->users()->attach([1,3]);
        $id=$other->messages()->create(['user_id'=>1,'message'=>'Secret'])->id;
    } elseif ($invalid==='deleted') $this->message->delete();
    elseif ($invalid==='hidden') $this->message->hiddenByUsers()->attach(2);
    elseif ($invalid==='unknown') $id=999;
    else $id='not-an-id';
    $count=ChatMessage::withTrashed()->count();
    $data=['reply_to_message_id'=>$id];
    if ($voice) $data['audio']=UploadedFile::fake()->create('voice.webm',12,'audio/webm');
    else $data['attachment']=UploadedFile::fake()->create('document.pdf',12,'application/pdf');
    $this->postJson(route($voice?'chat.voice.send':'chat.messages.send',$this->conversation),$data)->assertUnprocessable()->assertJsonValidationErrors('reply_to_message_id');
    expect(ChatMessage::withTrashed()->count())->toBe($count)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['foreign','deleted','hidden','unknown','invalid'])->with([false,true]);

it('does not expose the original text after a quoted message is deleted or hidden', function ($mode) {
    $this->postJson(route('chat.messages.send',$this->conversation),['message'=>'Response','reply_to_message_id'=>$this->message->id])->assertOk();
    if ($mode==='deleted') $this->message->delete();
    else $this->message->hiddenByUsers()->attach(2);
    $result=$this->getJson(route('chat.messages',$this->conversation))->assertOk();
    $reply=collect($result->json('messages'))->firstWhere('message','Response');
    expect($reply['reply_to'])->toBe(['id'=>$this->message->id,'unavailable'=>true]);
    if ($mode==='hidden') $this->actingAs(User::find(1))->getJson(route('chat.messages',$this->conversation))->assertOk()
        ->assertJsonPath('messages.1.reply_to.message','Please review this.');
})->with(['deleted','hidden']);

it('blocks forged cross-conversation quotes when reading history', function () {
    $other=ChatConversation::create(['type'=>'direct','created_by'=>3]); $other->users()->attach(3);
    $secret=$other->messages()->create(['user_id'=>3,'message'=>'Secret']);
    $this->message->update(['reply_to_message_id'=>$secret->id]);
    $this->getJson(route('chat.messages',$this->conversation))->assertOk()
        ->assertJsonPath('messages.0.reply_to',['id'=>$secret->id,'unavailable'=>true])->assertDontSee('Secret');
});

it('allows replying to a message outside the loaded message window', function () {
    for ($i=0;$i<205;$i++) $this->conversation->messages()->create(['user_id'=>1,'message'=>'Other message '.$i]);
    $original=ChatMessage::latest('id')->firstOrFail();
    $this->postJson(route('chat.messages.send',$this->conversation),['message'=>'Reply','reply_to_message_id'=>$original->id])->assertOk()
        ->assertJsonPath('reply_to.message',$original->message);
});

it('requires current conversation membership and authentication for replies', function () {
    $data=['message'=>'Reply','reply_to_message_id'=>$this->message->id];
    $this->actingAs(User::find(3))->postJson(route('chat.messages.send',$this->conversation),$data)->assertForbidden();
    $this->conversation->users()->detach(2);
    $this->actingAs(User::find(2))->postJson(route('chat.messages.send',$this->conversation),$data)->assertForbidden();
    auth()->forgetGuards();
    $this->postJson(route('chat.messages.send',$this->conversation),$data)->assertUnauthorized();
    expect(ChatMessage::count())->toBe(1);
});
