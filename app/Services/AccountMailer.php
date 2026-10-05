<?php

namespace App\Services;

use App\Mail\AccountNotice;
use App\Models\Bill;
use App\Models\User;
use App\Models\UserAccounts;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountMailer
{
    public function send(
        ?string $email,
        string $subject,
        string $message,
        ?string $greeting = null,
        ?string $actionUrl = null,
        ?string $actionText = null,
        array $details = [],
    ): void {
        $email = trim((string) $email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new AccountNotice(
                $subject,
                $message,
                $greeting,
                $actionUrl,
                $actionText,
                $details,
            ));
        } catch (\Throwable $e) {
            Log::warning('Unable to send account email.', [
                'email' => $email,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyEmailOtp(?string $email, ?string $name, string $code): void
    {
        $this->send(
            $email,
            'Your verification code',
            "Your Sta. Rita Water District registration was received.\n\nEnter this code on the verification page to finish creating your account. It expires in 10 minutes.\n\nIf you did not register, you can ignore this email.",
            $this->hello($name),
            null,
            null,
            ['Verification code' => $code],
        );
    }

    public function notifyRegistration(User $user): void
    {
        $this->send(
            $user->email,
            'We received your registration',
            "Thank you for registering with Sta. Rita Water District through NovuStream.\n\nYour application is waiting for review. You can sign in at any time to check its status. We will email you again when the district approves or does not approve the application.\n\nIf you did not create this account, you can ignore this message.",
            $this->hello($user->name),
            url('/login'),
            'Sign in',
        );

        $this->sendVerification($user);
    }

    public function sendVerification(User $user): void
    {
        if (!$user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Unable to send verification email.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyProfileUpdated(?string $email, bool $passwordChanged, ?string $name = null): void
    {
        if ($passwordChanged) {
            $message = "Your NovuStream profile was updated, and your password was changed.\n\nIf you did not make this change, reset your password or contact Sta. Rita Water District right away.";
            $subject = 'Your password was changed';
        } else {
            $message = "Your NovuStream profile for Sta. Rita Water District was updated.\n\nIf you did not make this change, contact the district office so we can review the account.";
            $subject = 'Your profile was updated';
        }

        $this->send(
            $email,
            $subject,
            $message,
            $this->hello($name),
            $passwordChanged ? url('/forgot-password') : url('/login'),
            $passwordChanged ? 'Reset password' : 'Sign in',
        );
    }

    public function notifyServiceApplication(?string $email, ?string $applicationNo, ?string $name = null): void
    {
        $number = $applicationNo ?: 'Pending number';

        $this->send(
            $email,
            'Service application submitted',
            "We received your water service connection application. It is now pending review.\n\nKeep your contact number available in case the district needs another document. You can follow the application from your account overview after you sign in.",
            $this->hello($name),
            url('/login'),
            'View your account',
            [
                'Application number' => $number,
                'Status' => 'Pending review',
            ],
        );
    }

    public function notifyAccountCreated(?string $email, ?string $name = null): void
    {
        $this->send(
            $email,
            'Your concessionaire account is ready',
            "Sta. Rita Water District created an online account for you on NovuStream.\n\nSign in with this email address and the password given by the district office. After you sign in, change that password so only you can use the account.",
            $this->hello($name),
            url('/login'),
            'Sign in',
        );
    }

    public function notifyAccountUpdated(?string $email, ?string $name = null): void
    {
        $this->send(
            $email,
            'Your account details were updated',
            "The district updated the concessionaire information on your account.\n\nSign in to review the details. If you did not ask for this change, contact Sta. Rita Water District.",
            $this->hello($name),
            url('/login'),
            'Review your account',
        );
    }

    public function notifyApplicationDecision(?string $email, ?string $name, string $decision, ?string $reason = null): void
    {
        $approved = $decision === 'approved';

        $message = $approved
            ? "Sta. Rita Water District approved your concessionaire application.\n\nYou can now sign in to view your bills, statements, and payments."
            : "Sta. Rita Water District did not approve your concessionaire application.\n\nPlease contact the district office if you need help before you apply again.";

        $details = [
            'Decision' => $approved ? 'Approved' : 'Not approved',
        ];

        if (!$approved && filled($reason)) {
            $details['Reason'] = $reason;
        }

        $this->send(
            $email,
            $approved ? 'Your application was approved' : 'Your application was not approved',
            $message,
            $this->hello($name),
            url('/login'),
            $approved ? 'Go to your account' : 'Sign in',
            $details,
        );
    }

    public function notifyBillDue(Bill $bill): void
    {
        $accountNo = $this->accountNo($bill) ?: 'your account';
        $amount = number_format((float) ($bill->amount ?? $bill->total ?? 0), 2);

        $this->send(
            $this->emailForAccount($accountNo),
            'Your water bill is ready',
            "A new statement of account is ready for your Sta. Rita Water District account.\n\nSign in to NovuStream to view the statement and pay online.",
            'Hello,',
            url('/login'),
            'View and pay',
            [
                'Account number' => $accountNo,
                'Reference number' => $bill->reference_no,
                'Amount due' => 'PHP '.$amount,
                'Due date' => $this->formatDate($bill->due_date),
            ],
        );
    }

    public function notifyPaymentPosted(Bill $bill): void
    {
        $accountNo = $this->accountNo($bill) ?: 'your account';
        $amount = number_format((float) ($bill->amount_paid ?? $bill->amount ?? 0), 2);

        $this->send(
            $this->emailForAccount($accountNo),
            'We posted your payment',
            "Your payment was posted to your Sta. Rita Water District account.\n\nThank you. Sign in to view the payment on your account overview.",
            'Hello,',
            url('/login'),
            'View payment',
            [
                'Account number' => $accountNo,
                'Reference number' => $bill->reference_no,
                'Amount posted' => 'PHP '.$amount,
                'Date posted' => $this->formatDate($bill->date_paid, true) ?: now()->timezone('Asia/Manila')->format('M d, Y h:i A'),
            ],
        );
    }

    private function hello(?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? 'Hello '.$name.',' : 'Hello,';
    }

    private function formatDate(mixed $value, bool $withTime = false): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->timezone('Asia/Manila')
                ->format($withTime ? 'M d, Y h:i A' : 'M d, Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function accountNo(Bill $bill): ?string
    {
        if (!empty($bill->account_no)) {
            return (string) $bill->account_no;
        }

        $bill->loadMissing('reading');

        return $bill->reading->account_no ?? null;
    }

    private function emailForAccount(?string $accountNo): ?string
    {
        if ($accountNo === null || $accountNo === '' || $accountNo === 'your account') {
            return null;
        }

        $account = UserAccounts::with('user')->where('account_no', $accountNo)->first();

        return $account?->user?->email;
    }
}
