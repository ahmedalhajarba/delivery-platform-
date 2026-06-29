<?php

namespace App\Listeners;

use App\Events\OrderProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderProcessed  $event
     * @return void
     */
    public function handle(OrderProcessed $event)
    {
        $order = $event->order ;

        $token='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjEwNSwidXNlclR5cGUiOiJ0cmFkZXIiLCJhcGkiOnRydWUsImlhdCI6MTYxMjY0NTI0M30.3z7qfiHhktqgj22Ef5pdEGllNgvVclEKm3HePn8h03s';
        $value='application/json';

        $response = Http::withHeaders([
            'token' => $token,
        ])->accept('application/json')->post('/https://awfarxpress.com/api/v1/dev/add-package', [
            'senderName'=>'aseel',
            'customerName'=>'aseel',
            'mobile'=>'0597248556',
            'city'=>'GAZA',
            'noOfPackages'=>5,
            'neighborhood' =>'GAZA',
            'weight'=>11,
            'needPay'=>true,
            'price'=>30,
            'packagePrice'=>40,
            'packageContent'=>'t',
        ]);
        $orderApi= Order::find($order->id);
        $orderApi->update(['response'=>$response]);

    }
}
