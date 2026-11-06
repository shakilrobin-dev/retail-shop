<?php

namespace App\Services;

use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional SMS through any HTTP SMS gateway (most Bangladeshi gateways work).
 *
 * The API URL may use placeholders, e.g.
 *   https://bulksmsbd.net/api/smsapi?api_key={api_key}&type=text&number={phone}&senderid={sender}&message={message}
 * Sending is skipped when SMS is turned off in settings.
 */
class SmsService
{
    /** Events the admin can switch on. */
    public const EVENTS = [
        'repair_received' => 'Repair job received',
        'repair_ready' => 'Repair is ready for pickup',
        'sale_receipt' => 'Sale receipt',
    ];

    public function __construct(private Settings $settings) {}

    public function isEnabledFor(string $event, ?int $branchId = null): bool
    {
        return $this->settings->bool('sms_enabled', $branchId)
            && in_array($event, (array) $this->settings->get('sms_events', $branchId), true);
    }

    public function send(string $phone, string $message, ?string $event = null, ?int $branchId = null, bool $force = false): SmsLog
    {
        $log = SmsLog::query()->create([
            'branch_id' => $branchId,
            'phone' => $phone,
            'message' => $message,
            'event' => $event,
            'status' => 'pending',
        ]);

        if (! $force && ! $this->settings->bool('sms_enabled', $branchId)) {
            $log->update(['status' => 'skipped']);

            return $log;
        }

        $url = (string) $this->settings->get('sms_api_url', $branchId);

        if ($url === '') {
            $log->update(['status' => 'failed', 'response' => 'No SMS API URL in settings.']);

            return $log;
        }

        $replacements = [
            '{phone}' => urlencode($this->normalizePhone($phone)),
            '{message}' => urlencode($message),
            '{api_key}' => urlencode((string) $this->settings->get('sms_api_key', $branchId)),
            '{sender}' => urlencode((string) $this->settings->get('sms_sender_id', $branchId)),
        ];

        try {
            $response = Http::timeout(8)->get(strtr($url, $replacements));
            $log->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'response' => str($response->body())->limit(1000)->toString(),
            ]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'response' => $exception->getMessage()]);
        }

        return $log;
    }

    public function repairReceived(RepairJob $job): void
    {
        if ($job->customer?->phone && $this->isEnabledFor('repair_received', $job->branch_id)) {
            $this->send($job->customer->phone, __('Dear :name, we received your :device. Job no: :job. - :shop', [
                'name' => $job->customer->name,
                'device' => trim($job->device_brand.' '.$job->device_model),
                'job' => $job->job_no,
                'shop' => $this->settings->get('shop_name'),
            ]), 'repair_received', $job->branch_id);
        }
    }

    public function repairReady(RepairJob $job): void
    {
        if ($job->customer?->phone && $this->isEnabledFor('repair_ready', $job->branch_id)) {
            $this->send($job->customer->phone, __('Dear :name, your :device is ready. Job no: :job. Due: :due. - :shop', [
                'name' => $job->customer->name,
                'device' => trim($job->device_brand.' '.$job->device_model),
                'job' => $job->job_no,
                'due' => money(max(0, (float) $job->total - (float) $job->paid_amount)),
                'shop' => $this->settings->get('shop_name'),
            ]), 'repair_ready', $job->branch_id);
        }
    }

    public function saleReceipt(Sale $sale): void
    {
        if ($sale->customer?->phone && $this->isEnabledFor('sale_receipt', $sale->branch_id)) {
            $this->send($sale->customer->phone, __('Thank you for shopping. Invoice :no, total :total. - :shop', [
                'no' => $sale->invoice_no,
                'total' => money($sale->total),
                'shop' => $this->settings->get('shop_name'),
            ]), 'sale_receipt', $sale->branch_id);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '01') ? '88'.$digits : $digits;
    }
}
