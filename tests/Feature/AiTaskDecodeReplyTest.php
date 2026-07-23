<?php

namespace NickDeKruijk\Leap\Tests\Feature;

use NickDeKruijk\Leap\Classes\AiTask;
use NickDeKruijk\Leap\Tests\TestCase;

class AiTaskDecodeReplyTest extends TestCase
{
    public function test_decodes_a_plain_json_reply(): void
    {
        $this->assertSame(['a' => 'b'], AiTask::decodeReply('{"a":"b"}'));
    }

    public function test_decodes_a_fenced_json_reply(): void
    {
        $this->assertSame(['a' => 'b'], AiTask::decodeReply("```json\n{\"a\":\"b\"}\n```"));
    }

    public function test_decodes_nested_objects(): void
    {
        $this->assertSame(['a' => ['b' => 'c']], AiTask::decodeReply('Sure! {"a":{"b":"c"}}'));
    }

    public function test_trailing_prose_with_a_brace_does_not_corrupt_the_decode(): void
    {
        // A greedy /{.*}/s match would span up to the last "}" and fail to decode.
        $reply = '{"a":"b"}'."\n".'Note: keep {placeholders} intact}';

        $this->assertSame(['a' => 'b'], AiTask::decodeReply($reply));
    }

    public function test_returns_null_when_no_json_object_is_present(): void
    {
        $this->assertNull(AiTask::decodeReply('No object here'));
        $this->assertNull(AiTask::decodeReply(''));
    }
}
