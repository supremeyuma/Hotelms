<?php
// app/Services/ContentService.php
namespace App\Services;

use App\Models\Content;

class ContentService
{
    public function all(): array
    {
        return Content::pluck('value', 'key')->toArray();
    }
}
