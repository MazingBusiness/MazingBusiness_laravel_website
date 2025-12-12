<?php

namespace App\Mail;

use App\Models\BlDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CiPlZipMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\BlDetail */
    public $bl;

    /** @var string */
    public $zipPath;

    /** @var string */
    public $zipName;

    /**
     * Create a new message instance.
     */
    public function __construct(BlDetail $bl, string $zipPath, string $zipName)
    {
        $this->bl      = $bl;
        $this->zipPath = $zipPath;
        $this->zipName = $zipName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = 'CI + PL ZIP for BL ' . ($this->bl->bl_no ?? $this->bl->id);

        return $this->view('emails.import.ci_pl_zip')   // simple blade view, see below
            ->subject($subject)
            ->with([
                'bl' => $this->bl,
            ])
            ->attach($this->zipPath, [
                'as'   => $this->zipName,
                'mime' => 'application/zip',
            ]);
    }
}
