<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Attachments;

use App\Domain\File\Actions\AttachFile;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('upload_attachment')]
#[Description('Attach a file to a task. Send the file as base64. Accepted: images, PDF, plain text, CSV, ZIP and Office documents, within the size limit Larasan is configured with.')]
final class UploadAttachment extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'filename' => $schema->string()->description('File name with extension, e.g. report.pdf.')->required(),
            'content_base64' => $schema->string()->description('The file contents, base64-encoded.')->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        if ($actor->cannot('attach', $task)) {
            throw ToolRefusal::forbidden('attach files to this task');
        }

        $input = $request->validate([
            'filename' => ['required', 'string', 'max:'.AttachFile::MAX_NAME_LENGTH, 'not_regex:/[\/\\\\\x00]/'],
            'content_base64' => ['required', 'string'],
        ]);

        $maxBytes = (int) config('attachments.max_kilobytes') * 1024;

        // Base64 is a third larger than the bytes it carries; refuse an oversized payload before decoding it.
        if (strlen($input['content_base64']) > intdiv($maxBytes * 4, 3) + 4) {
            throw new ToolRefusal('The file is larger than '.round($maxBytes / 1024 / 1024).' MB.');
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $input['content_base64']) ?? '', strict: true);

        if ($contents === false || $contents === '') {
            throw new ToolRefusal('content_base64 is not valid base64 or is empty.');
        }

        Throttle::hit('uploads', $actor, 20);

        $path = tempnam(sys_get_temp_dir(), 'larasan-mcp-');

        try {
            file_put_contents($path, $contents);
            $upload = new UploadedFile($path, $input['filename'], null, null, test: true);

            Validator::validate(['file' => $upload], [
                'file' => [
                    'file',
                    'max:'.config('attachments.max_kilobytes'),
                    'mimetypes:'.implode(',', (array) config('attachments.mime_types')),
                ],
            ], [
                'file.mimetypes' => 'Larasan does not accept this type of file.',
            ]);

            if (mb_strlen($upload->getClientOriginalExtension()) > AttachFile::MAX_EXTENSION_LENGTH) {
                throw new ToolRefusal('The file extension is too long.');
            }

            $attachment = app(AttachFile::class)->handle($task, $actor, $upload);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return $this->reply(['attachment' => Present::attachment($attachment->load('file'))]);
    }
}
