<?php
namespace App\Http\Helper;
use Illuminate\Http\Request;
use Yajra\DataTables\Utilities\Helper;
use Illuminate\Support\Facades\Http;
class Sms {

    public $username = 966540000856;
    public $password = '1234@Lstv';

    public function __construct($username,$password)
    {
        $this->username =$username;
        $this->password =$password;

    }

    public function store(Request $request,$username,$password){

        $response = Http::post('/https://www.hisms.ws/api.php', [
            'username'=>$username,
            'password'=>$password,
            'new_password'=>$password,
            'numbers'=>$request->numbers,
            'sender'=>$request->sender,
            'message' =>$request->message,
            'send_sms'=>$request->send_sms,
            'date'=>$request->date,
            'time'=>$request->time,

        ]);


        return response($response);

    }

}