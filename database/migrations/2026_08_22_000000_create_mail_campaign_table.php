<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chiến dịch gửi mail marketing từ CMS.
 *
 * mt219 (`Http/Controllers/Cms/MailController`) gửi xong là XONG — không lưu
 * lại đã gửi gì, cho ai, bao nhiêu cái thành công. Khách khiếu nại "sao tôi
 * nhận mail này" thì không có gì để tra. Bảng này là bản ghi chiến dịch;
 * `mail_campaign_recipient` là bản ghi từng người nhận.
 *
 * `send_to` giữ nguyên vocabulary của mt219 để đọc log cũ không phải dịch:
 *   newsletter | user_all | user_group | user | file
 * (App\Enums\MailCampaignSendTo).
 *
 * `status` TINYINT: 1=queued, 2=sending, 3=completed, 4=failed
 * (App\Enums\MailCampaignStatus).
 *
 * `recipient_count` chốt tại thời điểm tạo — KHÔNG đếm lại từ bảng recipient
 * mỗi lần xem: danh sách khách thay đổi liên tục, con số phải là ảnh chụp của
 * lúc bấm gửi thì mới đối chiếu được với sent_count.
 *
 * KHÔNG FK sang `user` (created_by) theo convention repo: bảng `user` là
 * legacy, các bảng khác cũng không ràng buộc FK sang nó.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mail_campaign')) {
            return;
        }

        Schema::create('mail_campaign', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('subject', 255);
            $table->longText('message');

            $table->string('send_to', 32);
            $table->integer('user_group_id')->unsigned()->nullable();

            $table->integer('recipient_count')->unsigned()->default(0);
            $table->integer('sent_count')->unsigned()->default(0);
            $table->integer('failed_count')->unsigned()->default(0);

            $table->tinyInteger('status')->unsigned()->default(1);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'idx_mc_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_campaign');
    }
};
