<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Attachments;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('download_attachment')]
#[Description('Read an attached file. Images come back as images, text and CSV as text, other files as base64. Files over the inline limit return only their details and a download link.')]
#[IsReadOnly]
final class DownloadAttachment extends LarasanTool
{
    /**
     * SVG is never on this list: it is not an accepted upload type, and is script when rendered.
     *
     * @var list<string>
     */
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'attachment_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $attachment = Find::attachment($actor, $request->string('attachment_id')->toString());
        $file = $attachment->file;
        $details = Present::attachment($attachment);

        if ((int) $file->size > (int) config('larasan-mcp.download_max_kilobytes') * 1024) {
            return $this->reply(['attachment' => $details, 'inline' => false, 'reason' => 'The file is larger than the inline limit; use download_url.']);
        }

        $contents = Storage::disk($file->disk)->get($file->path)
            ?? throw new ToolRefusal('The stored file is missing.');

        if (in_array($file->mime_type, self::IMAGE_TYPES, true)) {
            return Response::make([
                Response::json(['attachment' => $details]),
                Response::image($contents, $file->mime_type),
            ]);
        }

        if (in_array($file->mime_type, ['text/plain', 'text/csv'], true) && mb_check_encoding($contents, 'UTF-8')) {
            return $this->reply(['attachment' => $details, 'inline' => true, 'text' => $contents]);
        }

        return $this->reply(['attachment' => $details, 'inline' => true, 'content_base64' => base64_encode($contents)]);
    }
}
