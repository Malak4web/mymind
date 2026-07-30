<?php

namespace App\Console\Commands;

use App\Models\BatchedEmail;
use App\Models\EmailDigestQueue;
use Illuminate\Console\Command;

class SendEmailDigest extends Command
{
    protected $signature = 'digest:send';
    protected $description = 'جمع التحديثات المعلقة وإرسالها كبريد ملخص موحد';

    public function handle()
    {
        $queueItems = EmailDigestQueue::with('task')->get();
        $count = $queueItems->count();

        if ($count === 0) {
            $this->info('لا توجد تحديثات معلقة للإرسال.');
            return 0;
        }

        // Format body text
        $body = "ملخص تحديثات مشروع ماي مايند:\n\n";
        foreach ($queueItems as $item) {
            $taskTitle = $item->task ? $item->task->title : 'مهمة مجهولة';
            $body .= "• مهمة: [{$taskTitle}] - {$item->update_text}\n";
        }

        $sentAt = now()->format('Y-m-d H:i:s');
        $subject = 'ملخص تحديثات المهام المجمع - ' . $sentAt;

        // Save batched email log in DB so frontend console log is fed
        BatchedEmail::create([
            'sent_at' => $sentAt,
            'subject' => $subject,
            'body' => $body,
            'count' => $count
        ]);

        // Clear queue
        EmailDigestQueue::truncate();

        $this->info("تم إرسال البريد الملخص بنجاح لعدد {$count} من التحديثات.");
        return 0;
    }
}
