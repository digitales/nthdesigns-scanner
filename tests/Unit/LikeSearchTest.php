<?php

namespace Tests\Unit;

use App\Support\LikeSearch;
use Tests\TestCase;

class LikeSearchTest extends TestCase
{
    public function test_column_expression_for_like_leaves_column_unchanged_by_default(): void
    {
        $this->assertSame('token', LikeSearch::columnExpressionForLike('pgsql', 'token'));
        $this->assertSame('business_name', LikeSearch::columnExpressionForLike('sqlite', 'business_name'));
    }

    public function test_column_expression_for_like_casts_to_text_on_postgresql(): void
    {
        $this->assertSame('token::text', LikeSearch::columnExpressionForLike('pgsql', 'token', asText: true));
    }

    public function test_column_expression_for_like_does_not_cast_on_sqlite(): void
    {
        $this->assertSame('token', LikeSearch::columnExpressionForLike('sqlite', 'token', asText: true));
    }
}
