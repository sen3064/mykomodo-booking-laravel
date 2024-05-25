<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use PDF;
use App\Models\Payment;

class OrderTourMail extends Mailable
{
    public Booking $bookingss;
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($bookingss)
    {
        //
        $this->bookingss = $bookingss;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $payment = Payment::where('code',$this->bookingss->code)->first();
        $subject = 'Booking Confirmation '.$payment->code;
        $pdf = PDF::loadView('mails.tour-mail',['bookingss'=>$this->bookingss]);
        $pdf_name = 'Tagihan-'.$payment->bill_no.'.pdf';
        if($payment->status=='Payment Sukses'){
            $subject = 'Pembayaran Booking '.$payment->code;
            $pdf_name = 'Invoice_Kabtour-'.$payment->bill_no.'.pdf';
        }
        return $this->subject($subject)
        ->view('mails.tour-mail')
        ->attachData($pdf->output(), $pdf_name);
    }
}
