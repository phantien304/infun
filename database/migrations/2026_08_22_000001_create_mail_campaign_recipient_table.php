<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Từng người nhận của một chiến dịch — đây mới là thứ trả lời được câu
 * "đã gửi cho ai, cái nào lỗi vì sao".
 *
 * Vai trò thứ hai quan trọng không kém: HÀNG ĐỢI. Job gửi theo lô đọc đúng
 * các dòng `status = 0` (pending) của lô mình phụ trách, gửi xong ghi lại
 * status. Job chạy lại (retry, worker chết giữa chừng) sẽ bỏ qua dòng đã
 * `sent` ⇒ KHÔNG gửi trùng cho khách — thứ mà bản mt219 (dispatch 1 job/1
 * email, không có state) không đảm bảo được.
 *
 * UNIQUE (campaign_id, email): một người chỉ nhận một lần trong cùng chiến
 * dịch, kể cả khi trùng giữa "nhóm khách" và "file upload".
 *
 * `status` TINYINT: 0=pending, 1=sent, 2=failed.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mail_campaign_recipient')) {
            return;
        }

        Schema::create('mail_campaign_recipient', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('campaign_id');
            $table->string('email', 191);
            $table->unsignedBigInteger('user_id')->nullable();

            $table->tinyInteger('status')->unsigned()->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreign('campaign_id', 'fk_mcr_campaign')
                ->references('id')->on('mail_campaign')->cascadeOnDelete();

            $table->unique(['campaign_id', 'email'], 'uq_mcr_campaign_email');
            $table->index(['campaign_id', 'status'], 'idx_mcr_campaign_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_campaign_recipient');
    }
};
