<?php

declare(strict_types=1);

use App\Domain\File\Models\Attachment;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Larasan\Mcp\Tools\Attachments\DeleteAttachment;
use Larasan\Mcp\Tools\Attachments\DownloadAttachment;
use Larasan\Mcp\Tools\Attachments\ListAttachments;
use Larasan\Mcp\Tools\Attachments\UploadAttachment;

beforeEach(function (): void {
    Storage::fake((string) config('filesystems.attachments'));
    Queue::fake();
});

function onePixelPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

it('uploads, lists and downloads an image', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();

    $uploaded = mcpData($owner, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'pixel.png',
        'content_base64' => base64_encode(onePixelPng()),
    ])['attachment'];

    expect($uploaded['mime_type'])->toBe('image/png')
        ->and(array_column(mcpData($owner, ListAttachments::class, ['task_id' => $task->id])['attachments'], 'id'))->toBe([$uploaded['id']]);

    mcpCall($owner, DownloadAttachment::class, ['attachment_id' => $uploaded['id']])
        ->assertOk()
        ->assertSee('pixel.png');
});

it('returns a text file as text', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $uploaded = mcpData($owner, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'notes.txt',
        'content_base64' => base64_encode("Meeting notes\nline two"),
    ])['attachment'];

    $data = mcpData($owner, DownloadAttachment::class, ['attachment_id' => $uploaded['id']]);

    expect($data['text'])->toBe("Meeting notes\nline two");
});

it('rejects a file type Larasan does not accept, and broken base64', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();

    mcpCall($owner, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'run.sh',
        'content_base64' => base64_encode("\x7fELF\x02\x01\x01\x00".str_repeat("\x00", 64)),
    ])->assertHasErrors();

    mcpCall($owner, UploadAttachment::class, ['task_id' => $task->id, 'filename' => 'x.txt', 'content_base64' => '***'])
        ->assertHasErrors(['content_base64 is not valid base64 or is empty.']);

    mcpCall($owner, UploadAttachment::class, ['task_id' => $task->id, 'filename' => '../escape.txt', 'content_base64' => base64_encode('x')])
        ->assertHasErrors();

    expect(Attachment::query()->count())->toBe(0);
});

it('refuses an upload larger than the attachment limit before decoding it', function (): void {
    config(['attachments.max_kilobytes' => 1]);
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();

    mcpCall($owner, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'big.txt',
        'content_base64' => base64_encode(str_repeat('a', 2048)),
    ])->assertHasErrors();
});

it('keeps another workspace\'s files out of reach', function (): void {
    [, $owner] = mcpWorkspace();
    [$otherWorkspace, $stranger] = mcpWorkspace();
    $task = Task::factory()->in($otherWorkspace)->create();
    $uploaded = mcpData($stranger, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'secret.txt',
        'content_base64' => base64_encode('top secret'),
    ])['attachment'];

    mcpCall($owner, DownloadAttachment::class, ['attachment_id' => $uploaded['id']])
        ->assertHasErrors(['No attachment with that id is visible to you.'])
        ->assertDontSee('top secret');
    mcpCall($owner, DeleteAttachment::class, ['attachment_id' => $uploaded['id']])->assertHasErrors();
    mcpCall($owner, UploadAttachment::class, ['task_id' => $task->id, 'filename' => 'x.txt', 'content_base64' => base64_encode('x')])
        ->assertHasErrors();

    expect(Attachment::query()->count())->toBe(1);
});

it('removes an attachment', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $uploaded = mcpData($owner, UploadAttachment::class, [
        'task_id' => $task->id,
        'filename' => 'a.txt',
        'content_base64' => base64_encode('Some plain text'),
    ])['attachment'];

    mcpData($owner, DeleteAttachment::class, ['attachment_id' => $uploaded['id']]);

    expect(Attachment::query()->find($uploaded['id']))->toBeNull();
});
