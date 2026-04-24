<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send Payment Reminders Command
 *
 * Sends email reminders for overdue invoices.
 */
class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:send-reminders 
                            {--days=7 : Minimum days overdue}
                            {--dry-run : Preview reminders without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send payment reminder emails for overdue invoices';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $daysOverdue = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Searching for overdue invoices...');

        // Get overdue invoices
        $overdueInvoices = Invoice::query()
            ->with(['customer.user', 'items', 'payments'])
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', now()->subDays($daysOverdue))
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('✅ No overdue invoices found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$overdueInvoices->count()} overdue invoice(s):");
        $this->newLine();

        $remindersSent = 0;

        foreach ($overdueInvoices as $invoice) {
            $daysOverdueForInvoice = $invoice->due_date->diffInDays(now());
            $email = $invoice->customer->user->email;
            
            $this->line(sprintf(
                '  • Invoice %s: %s (€ %.2f) - %d days overdue - Customer: %s',
                $invoice->invoice_number,
                $invoice->due_date->format('d.m.Y'),
                $invoice->remaining_balance,
                $daysOverdueForInvoice,
                $email
            ));

            if (!$dryRun) {
                Mail::to($email)->queue(new PaymentReminder($invoice));
                $remindersSent++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('🔍 Dry run mode - no emails were sent');
            $this->info("Would have sent {$overdueInvoices->count()} reminder(s)");
        } else {
            $this->info("✅ Successfully queued {$remindersSent} payment reminder(s)");
        }

        return self::SUCCESS;
    }
}
