<?php

// app/
use App\Models\Content;

function content(string $key, $default = null) {
    return Content::where('key',$key)->value('value') ?? $default;
}
