<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyUserAlertRequest;
use App\Http\Requests\StoreUserAlertRequest;
use App\Models\User;
use App\Models\UserAlert;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Mail\MailUsers;
class UserAlertsController extends Controller
{

    public function index()
    {
        abort_if(Gate::denies('user_alert_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userAlerts = UserAlert::with(['users'])->get();

        return view('admin.userAlerts.index', compact('userAlerts'));
    }

    public function create()
    {
        abort_if(Gate::denies('user_alert_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::all()->pluck('name', 'id');

        return view('admin.userAlerts.create', compact('users'));
    }

    public function store(StoreUserAlertRequest $request)
    {
        $userAlert = UserAlert::create($request->all());
        $userAlert->users()->sync($request->input('users', []));
        $usersids = $request->input('users', []);
        foreach($usersids as $userid){
            $user = User::where('id',$userid)->first();
            $data =[
                'mobile'=>$user->mobile,
                'name'  =>$user->name,
            ];
            
            //  try {
            //             \Mail::to($user->email)->send(new MailUsers($data));
            
            //     } catch (Throwable $e) {
            //         report($e);
            
            //         return false;
            //     }
                
        $msg = '
        
        عملاء ميسان الكرام 
نود اعلامكم برجوع خدمة الشحن لجميع مناطق ومدن المملكة عن طريق شركاؤنا ،ونعتذر عن الانقطاع الحاصل بالخدمة بسبب  ظروف خارجة عن ارادتنا ونفيدكم بتغيير علامتنا التجارية من ميسان الى بيكتو 

اسم المستخدم/
'.$user->mobile.'
<br />
كلمة المرور/ 12341234


        '.url('/');    
        
        // dd($msg);
        $username = "966540000856";
        $password = "1234@Lstv";
        $message = $msg;
        $numbers = $user->mobile;
        $sender = "Maysan";
        //966133670671
        $message = urlencode($message) ;
        $numbers = preg_replace('/^05/', '9665', $numbers);
        // dd($numbers);
        $url="https://www.hisms.ws/api.php?send_sms&username=".$username."&password=".$password."&numbers=$numbers&sender=".$sender."&message=".$message;
        
        $result= file_get_contents($url);
        
                echo 'ok';
        }

        // return redirect()->route('admin.user-alerts.index');
    }

    public function show(UserAlert $userAlert)
    {
        abort_if(Gate::denies('user_alert_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userAlert->load('users');

        return view('admin.userAlerts.show', compact('userAlert'));
    }

    public function destroy(UserAlert $userAlert)
    {
        abort_if(Gate::denies('user_alert_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userAlert->delete();

        return back();
    }

    public function massDestroy(MassDestroyUserAlertRequest $request)
    {
        UserAlert::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function read(Request $request)
    {
        $alerts = \Auth::user()->userUserAlerts()->where('read', false)->get();
        foreach ($alerts as $alert) {
            $pivot       = $alert->pivot;
            $pivot->read = true;
            $pivot->save();
        }
    }
}
