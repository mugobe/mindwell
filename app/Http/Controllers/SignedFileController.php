<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Homework;
use App\Models\Resource;
use App\Services\CarePlan;

/**
 * Files for the mobile app (2026-11-07): the API hands out 30-minute signed links, so the
 * app can show a photo or open a PDF or audio file without sending its login token to the
 * browser. A link only works for the one file it was made for, and only until it expires.
 */
class SignedFileController extends Controller
{
    public function chat(ChatMessage $message)
    {
        return CarePlan::stream($message->attachment_path, $message->attachment_name, $message->attachment_mime);
    }

    public function resource(Resource $resource)
    {
        return CarePlan::stream($resource->file_path, $resource->file_name, $resource->file_mime);
    }

    public function homework(Homework $homework)
    {
        return CarePlan::stream($homework->reply_file_path, $homework->reply_file_name, $homework->reply_file_mime);
    }
}
