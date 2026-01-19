<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $code;

    public function __construct($user_name, $code)
    {
        $this->user_name = $user_name;
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject('Código de cambio de Contraseña')
                    ->view('emails.password-reset');
    }
}