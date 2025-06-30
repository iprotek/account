<?php

namespace iProtek\Account\Mailables;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CustomerMailable extends Mailable
{
    use Queueable, SerializesModels;
    public $tries = 1; 
 

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct( )
    {
        //
        //Kaizen Suggestion Approval | 000000123 - Sample
        //$this->subject= 'Helpdesk'.config('app.name')." | ".$ticket->title;  

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.customer-approved');
    }
}
