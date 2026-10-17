<?php

namespace App\Mail\Privacy;

use App\Models\DataBreach;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the operator inbox that a breach with risk was recorded and when the 72 hours end. No details of the breach. */
class DataBreachAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DataBreach $breach) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Datenpanne erfasst – Meldefrist bis '.$this->breach->reportDueAt()->timezone('Europe/Berlin')->format('d.m.Y H:i'));
    }

    public function content(): Content
    {
        return new Content(view: 'privacy.mail.data_breach_alert');
    }
}
