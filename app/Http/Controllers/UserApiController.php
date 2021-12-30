<?php

namespace App\Http\Controllers;

use App\SwitchPermission;
use App\Ticket;
use App\Unit;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use TCG\Voyager\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Models\Role;
use App\Models\File;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmail;
use App\Models\User as ModelsUser;

class UserApiController extends Controller
{
    //
    use AuthenticatesUsers;
    public function userLogin(Request $request)
    {
        $this->validateLogin($request);
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }
        $credentials = $this->credentials($request);
        if ($this->guard()->attempt($credentials, $request->has('remember'))) {
            $user = $this->userDetail($request->email, $request->tokenId);

            app('App\Http\Controllers\EmailController')->LoginNotification($request->email);
            return response()->json(["success" => true, "message" => "You have logged in successfully", "data" => $user]);
        }

        $this->incrementLoginAttempts($request);

        return response()->json(["success" => false]);
    }

    public function userDetail($email, $token)
    {
        $user = array();
        if ($email != "") {

            DB::table('users')
                ->where('email', $email)
                ->update([
                    'mobile_token' => $token
                ]);
            $user = User::where("email", $email)->first();
            return $user;
        }
    }

    public function tukarShift(Request $request)
    {
        $curTime = new \DateTime($request->date);
        $curTimeTo = new \DateTime($request->dateTo);
        $delegate = (string)$request->delegate;
        $id = $request->id;
        $tukarShift = array();
        $delegate = DB::select("SELECT * from users where users.name LIKE '%$delegate%'");
        if (count($delegate) > 0) {
            $delegate = $delegate[0]->id;
        }
        if ($id) {
            DB::table('switch_permissions')
                ->where('id', $id)
                ->update([
                    'date_to' => $curTimeTo->format("Y-m-d H:i:s")
                ]);
            $request->update = true;
        } else {

            DB::table('switch_permissions')->insert([
                'pemohon' => $request->user_id,
                'date' => $curTime->format("Y-m-d H:i:s"),
                'date_to' => $curTimeTo->format("Y-m-d H:i:s"),
                'description' => $request->description,
                'delegate' => $delegate,
                'shift_sched' => $request->shift,
                'next_approver' => $delegate,
                'status' => 1
            ]);
            $email = User::where("id", $delegate)->first()->email;
            $pemohon = User::select('name')->where('id', $request->user_id)->get();
            $delegate = User::select('name')->where('id', $delegate)->get();
            $tukarShift = [
                'pemohon' => $pemohon,
                'date' => $curTime->format("Y-m-d H:i:s"),
                'pemohon' => $curTimeTo->format("Y-m-d H:i:s"),
                'description' => $request->description,
                'delegate' => $delegate,
                'shift_sched' => $request->shift,
                'next_approver' => $delegate
            ];
            $request->update = false;

            // $email = User::where("id", $delegate)->first()->email;
            $data = [
                'email' => $email,
                'data' => $tukarShift,
                'status' => 'Approve',
                'next_approver' => $delegate,
                'pemohon' => false,
                'description' => ''
            ];
            app('App\Http\Controllers\EmailController')->index($data);
        }
        return response()->json(json_decode($request));
    }

    public function userOtp(Request $request)
    {
        if (!$request) {
            return;
        }
        $phoneNumber = $request->phone_number;
        $otp = $request->otp;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.k1nguniverse.com/api/v1/send?api_key=veoWXwRgiYOcsXa&api_pass=6rL8A2k0&module=SMS&sub_module=LONGNUMBER&sid=K1NGLONGOTP&destination=' . $phoneNumber . '&content=Your%20OTP%20is%20' . $otp,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        //echo $response;

        return response()->json(json_decode($response));
    }

    protected function guard()
    {
        return Auth::guard(app('VoyagerGuard'));
    }


    public function userShift(Request $request)
    {
        $uid = $request->uid;
        $tukarShift = array();
        if ($uid != "") {
            $tukarShift = collect(DB::select("SELECT *, ( SELECT COUNT(*) AS jumlah FROM switch_permissions WHERE pemohon = $uid  AND ( date_to > NOW()+ 1 OR date > NOW()+ 1 ) 	AND `status` != 4) as Jumlah FROM switch_permissions WHERE pemohon = $uid AND switch_permissions.`status` != 4 AND (switch_permissions.`date` >= '2021-11-28' OR ( switch_permissions.`date_to` >= '2021-11-28' OR switch_permissions.date_to IS NULL ) )"))->first();
            if ($tukarShift) {
                // $unitData = Unit::select('unit_number')->where('id', $value->id_unit)->get();
            } else {
                return [];
                //$tukarShift = collect(DB::select("SELECT * FROM switch_permissions WHERE delegate = $uid AND switch_permissions.`status` != 4 AND (switch_permissions.`date` >= '2021-11-28' OR ( switch_permissions.`date_to` >= '2021-11-28' OR switch_permissions.date_to IS NULL ) )"))->first();
                if ($tukarShift) {
                } else {
                    return [];
                }
            }
            $pemohon = User::select('name')->where('id', $tukarShift->pemohon)->get();
            $tukarShift->pemohon = (count($pemohon) > 0) ? $pemohon[0]->name : "";
            $delegate = User::select('name')->where('id', $tukarShift->delegate)->get();
            $tukarShift->delegate = (count($delegate) > 0) ? $delegate[0]->name : "";
            $next_approver = User::select('name')->where('id', $tukarShift->next_approver)->get();
            $tukarShift->next_approver = (count($next_approver) > 0) ? $next_approver[0]->name : "";

            //dd($tukarShift);
            if ($tukarShift) {
                return response()->json($tukarShift);
            }
            return [];
        }
        return [];
    }


    public function userRole(Request $request)
    {
        $uid = $request->uid;
        $user_role = array();
        $role = $request->role;
        if ($uid != "") {
            $user_role = DB::select("SELECT t1.id AS id, t1.name AS lev1, COUNT(t2.name) as lev2, COUNT(t3.name) as lev3, COUNT(t4.name) as lev4 FROM sb_cityres.users AS t1 LEFT JOIN sb_cityres.users AS t2 ON t2.supervisor = t1.id LEFT JOIN sb_cityres.users AS t3 ON t3.supervisor = t2.id LEFT JOIN sb_cityres.users AS t4 ON t4.supervisor = t3.id WHERE (t1.id = " . $uid . " AND t1.role_id = " . $role . ") GROUP BY t1.name,t1.id");
            return response()->json($user_role);
        }
        return [];
    }

    public function updateUser(Request $request)
    {
        $uid = $request->uid;
        if ($uid != "") {
            DB::table('users')
                ->where('id', $uid)
                ->update(['name' => $request->name]);
        }
        return [];
    }

    public function userTicketDelegate(Request $request)
    {
        $uid = $request->uid;
        $ticket = array();
        $role = $request->role;
        if ($uid != "") {
            $ticket = Ticket::where('pic', $uid)->where('status', 2)->get();
            foreach ($ticket as $key => $value) {
                # code...
                $getMonth = date("m", strtotime($value->created_at));
                $getDate = date("d", strtotime($value->created_at));
                $getYear = date("Y", strtotime($value->created_at));
                $ticket[$key]->tranNumber = "SCR-" . $getMonth . $getDate . $getYear . $value->id;
                $unitData = Unit::select('unit_number')->where('id', $value->id_unit)->get();
                $ticket[$key]->unit_name = (count($unitData) > 0) ? $unitData[0]->unit_number : "";
            }
            return response()->json($ticket);
        }
        return [];
    }


    public function userGroupSec(Request $request)
    {
        $uid = $request->uid;
        if ($uid != "") {
            $role =  DB::table('user_roles')->where('user_id', $uid)->get();
            return response()->json($role);
        }
        return [];
    }


    public function chatList(Request $request)
    {
        $uid = $request->uid;
        if ($uid != "") {
            $role =  DB::select("SELECT *,chat_privates.id as chat_id FROM chat_privates LEFT JOIN users ON id_user = users.id WHERE chat_privates.id IN( SELECT id FROM chat_privates WHERE id_user = $uid) AND id_user != $uid");
            return response()->json($role);
        }
        return [];
    }

    public function sendMail(Request $request)
    {
        $uid = $request->uid;
        // app('App\Http\Controllers\EmailController')->index($request);

        return [];
    }

    public function insertChatList(Request $request)
    {
        $uid = $request->uid;
        $uid2 = $request->uid2;
        $id = $request->id;
        //dump($uid);
        if ($uid != "") {
            $cekData = DB::select("SELECT * FROM chat_privates WHERE id IN (SELECT id from chat_privates where id_user = $uid ) and id_user = $uid2");
            if (count($cekData) > 0) {
                return response()->json($cekData);
            }

            DB::table('chat_privates')->insert([
                'id' => $id,
                'id_user' => $uid,
            ]);

            DB::table('chat_privates')->insert([
                'id' => $id,
                'id_user' => $uid2,
            ]);

            return response()->json([]);
        }
        return [];
    }

    public function approveShiftInfo(Request $request)
    {
        $uid = $request->uid;
        if ($uid != "") {
            $tukarShift = SwitchPermission::where("next_approver", $uid)->where("status", 1)->get();
            if ($tukarShift) {
                foreach ($tukarShift as $row) {
                    # code...
                    $pemohon = User::select('name')->where('id', $row->pemohon)->get();
                    $row->pemohon = (count($pemohon) > 0) ? $pemohon[0]->name : "";
                    $delegate = User::select('name')->where('id', $row->delegate)->get();
                    $row->delegate = (count($delegate) > 0) ? $delegate[0]->name : "";
                    $next_approver = User::select('name')->where('id', $row->next_approver)->get();
                    $row->next_approver = (count($next_approver) > 0) ? $next_approver[0]->name : "";
                }
            } else {
                return [];
            }
            return response()->json($tukarShift);
        }
        return [];
    }

    public function listShiftInfo(Request $request)
    {
        $uid = $request->uid;
        if ($uid != "") {
            $tukarShift = SwitchPermission::where("pemohon", $uid)->orwhere("delegate", $uid)->where("status", 1)->get();
            if ($tukarShift) {
                foreach ($tukarShift as $row) {
                    # code...
                    $pemohon = User::select('name')->where('id', $row->pemohon)->get();
                    $row->pemohon = (count($pemohon) > 0) ? $pemohon[0]->name : "";
                    $delegate = User::select('name')->where('id', $row->delegate)->get();
                    $row->delegate = (count($delegate) > 0) ? $delegate[0]->name : "";
                    $next_approver = User::select('name')->where('id', $row->next_approver)->get();
                    $row->next_approver = (count($next_approver) > 0) ? $next_approver[0]->name : "";
                }
            } else {
                return [];
            }
            return response()->json($tukarShift);
        }
        return [];
    }

    public function approveShift(Request $request)
    {
        $id = $request->id;
        DB::table('switch_permissions')
            ->where('id', $id)
            ->update(['name' => $request->name]);

        $email = User::where("id", $id)->first()->email;
        app('App\Http\Controllers\EmailController')->index($email);
        return response()->json(["Success" => true]);
    }

    public function upload(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:doc,docx,pdf,txt,csv,jpg|max:2048',
        ]);

        if ($validator->fails()) {

            return response()->json(['error' => $validator->errors()], 401);
        }


        if ($file = $request->file('file')) {
            $path = $file->store('public/files');
            $name = $file->hashName();

            return response()->json([
                "success" => true,
                "message" => "File successfully uploaded",
                "file" => $name
            ]);
        }
    }


    public function getUserDelegate(Request $request)
    {
        $uid = $request->uid;
        $user_role = array();
        $get_user = array();
        $role = $request->role;
        // dump($uid);
        $user = DB::select("SELECT DISTINCT id,user_roles.role_id,users.role_id FROM users LEFT JOIN user_roles ON user_roles.user_id = users.id
        WHERE user_id =" . $uid);
        if ($uid != "") {
            $user_role = DB::select("SELECT t1.id AS id, t1.name AS lev1, COUNT(t2.name) as lev2, COUNT(t3.name) as lev3, COUNT(t4.name) as lev4 FROM sb_cityres.users AS t1 LEFT JOIN sb_cityres.users AS t2 ON t2.supervisor = t1.id LEFT JOIN sb_cityres.users AS t3 ON t3.supervisor = t2.id LEFT JOIN sb_cityres.users AS t4 ON t4.supervisor = t3.id WHERE (t1.id = " . $uid . " AND t1.role_id = " . $role . ") GROUP BY t1.name,t1.id");
            if ($user_role[0]->lev2 > 0 && $user_role[0]->lev3 > 0) {
                // dump("dansek");
                $get_user = DB::select("SELECT id,role_group.role_id,lev1,lev2,lev3  FROM role_group LEFT JOIN user_roles ON user_roles.user_id = role_group.id WHERE lev2 > 0 AND lev3 > 0 AND role_group.role_id = " . $role . " AND id != " . $uid . " AND user_roles.role_id != " . $user[0]->role_id . " GROUP BY id,role_group.role_id,lev1,lev2,lev3,user_id");
            } elseif ($user_role[0]->lev2 > 0) {
                // dump('danru');
                $get_user = DB::select("SELECT id,role_group.role_id,lev1,lev2,lev3  FROM role_group LEFT JOIN user_roles ON user_roles.user_id = role_group.id WHERE lev2 > 0 AND lev3 = 0 AND role_group.role_id = " . $role . " AND id != " . $uid . " AND user_roles.role_id != " . $user[0]->role_id . " GROUP BY id,role_group.role_id,lev1,lev2,lev3,user_id");
            } else {
                // dump('anggota');
                $get_user = DB::select("SELECT id,role_group.role_id,lev1,lev2,lev3  FROM role_group LEFT JOIN user_roles ON user_roles.user_id = role_group.id WHERE lev2 = 0 AND lev3 = 0 AND role_group.role_id = " . $role . " AND id != " . $uid . " AND user_roles.role_id != " . $user[0]->role_id . " GROUP BY id,role_group.role_id,lev1,lev2,lev3,user_id");
            }
            // dump($get_user);
            return response()->json($get_user);
        }
        return [];
    }


    public function getTickets(Request $request)
    {
        $uid = $request->uid;
        $ticket = array();
        if ($uid != "") {
            $ticket = Ticket::where('id_pic', $uid)->whereNull('status')->get();
            if (count($ticket) == 0) {
                $ticket = Ticket::where('tickket_pics.user_id', $uid)
                    ->whereNull('status')
                    ->join('tickket_pics', 'tickket_pics.ticket_id', '=', 'tickets.id')
                    ->get();
            }
            foreach ($ticket as $key => $value) {
                # code...
                $getMonth = date("m", strtotime($value->created_at));
                $getDate = date("d", strtotime($value->created_at));
                $getYear = date("Y", strtotime($value->created_at));
                $ticket[$key]->tranNumber = "SCR-" . $getMonth . $getDate . $getYear . $value->id;
                $unitData = Unit::select('unit_number')->where('id', $value->id_unit)->get();
                $ticket[$key]->unit_name = (count($unitData) > 0) ? $unitData[0]->unit_number : "";
            }
            return response()->json($ticket);
        }
        return [];
    }


    public function getUpdateTickets(Request $request)
    {
        $id = $request->id;
        $realisasi = $request->realisasi;
        $realization_image = $request->realization_image;
        $result_image = $request->result_image;
        $penyelesaian = $request->penyelesaian != "" ? $request->penyelesaian : null;
        $status = null;
        if ($penyelesaian != null) {
            $status = 'Done';
        }
        $dataUpdate['realization'] = $realisasi;
        if ($penyelesaian != null) {
            $dataUpdate['result'] = $penyelesaian;
        }
        if ($status != null) {
            $dataUpdate['status'] = $status;
        }
        $dataUpdate['realization_image'] = $realization_image;
        $dataUpdate['result_image'] =  $result_image;
        if ($id != "") {
            // dump($get_user);
            DB::table('tickets')
                ->where('id', $id)
                ->update($dataUpdate);

            return response()->json($dataUpdate);
        }
        return response()->json($dataUpdate);
    }

    public function getApprovalWorkflow(Request $request)
    {
        # code...

        $uid = $request->uid;
        $user_role = array();
        $get_user = array();
        $role = $request->role;
        // dump($uid);
        if ($uid != "") {
            $user_role = DB::select("SELECT `t1`.`id` AS `id`, `t1`.`role_id` AS `role_id`, `t2`.`id` AS lev2, `t3`.`id` AS lev3 FROM((( `users` `t1` LEFT JOIN `users` `t2` ON (( `t2`.`id` = `t1`.`supervisor`))) LEFT JOIN `users` `t3` ON (( `t3`.`id` = `t2`.`supervisor` )))) WHERE `t2`.`name` IS NOT NULL AND t1.id = $uid GROUP BY `t1`.`id`, `t1`.`role_id`, `t1`.`name`");
            return response()->json($user_role);
        }
        return [];
    }

    public function getListApproval(Request $request)
    {
        $uid = $request->uid;
        $user_role = array();
        $get_user = array();
        $role = $request->role;
        // dump($uid);
        $tukarShift = SwitchPermission::where("next_approver", $uid)->get();
        return [];
    }

    public function approveTukarShift(Request $request)
    {

        $id = $request->id;
        $uid = $request->uid;
        $approve = $request->approve;
        $description = $request->description;
        $status = false;
        $tukarShift = SwitchPermission::where("id", $id)->first();
        $user_role = DB::select("SELECT `t1`.`id` AS `id`, `t1`.`role_id` AS `role_id`, `t2`.`id` AS lev2, `t3`.`id` AS lev3 FROM((( `users` `t1` LEFT JOIN `users` `t2` ON (( `t2`.`id` = `t1`.`supervisor`))) LEFT JOIN `users` `t3` ON (( `t3`.`id` = `t2`.`supervisor` )))) WHERE  t1.id = $tukarShift->pemohon GROUP BY `t1`.`id`, `t1`.`role_id`, `t1`.`name`,`t2`.`id`,`t3`.`id` limit 1");
        $user_role_delegate = DB::select("SELECT `t1`.`id` AS `id`, `t1`.`role_id` AS `role_id`, `t2`.`id` AS lev2, `t3`.`id` AS lev3 FROM((( `users` `t1` LEFT JOIN `users` `t2` ON (( `t2`.`id` = `t1`.`supervisor`))) LEFT JOIN `users` `t3` ON (( `t3`.`id` = `t2`.`supervisor` )))) WHERE `t2`.`name` IS NOT NULL AND t1.id = $tukarShift->delegate GROUP BY `t1`.`id`, `t1`.`role_id`, `t1`.`name`,`t2`.`id`,`t3`.`id` limit 1");
        $lev = $user_role[0]->lev2;
        $lev2 = $user_role_delegate[0]->lev2;
        $lev3 = $user_role[0]->lev3;
        $next_approver = $lev;
        if ($uid == $lev) {
            $next_approver = $lev2;
        } else if ($uid == $lev2) {
            $next_approver = 13;
        } else if ($uid == 13) {
            $next_approver = $lev3;
        }
        if ($uid == $lev3) {
            $status = 3;
        }
        if ($approve == "false") {
            $status = 4;
        }

        $data = [
            'id' => $id,
            'uid' => $uid,
            'approve' => $approve,
            'description' => $description,
            'status' => $status,
            'lev' => $lev,
            'lev2' => $lev2,
            'lev3' => $lev3,
            'next_approver' => $next_approver,
        ];

        if (!$approve) {
            DB::table('switch_permissions')
                ->where('id', $id)
                ->update(['next_approver' => null, 'status' => $status]);

            $email = User::where("id", $tukarShift->pemohon)->first()->email;
            $email_delegate = User::where("id", $tukarShift->delegate)->first()->email;

            $pemohon = User::select('name')->where('id', $tukarShift->pemohon)->get();
            $tukarShift->pemohon = (count($pemohon) > 0) ? $pemohon[0]->name : "";
            $delegate = User::select('name')->where('id', $tukarShift->delegate)->get();
            $tukarShift->delegate = (count($delegate) > 0) ? $delegate[0]->name : "";

            $data = [
                'email' => $email,
                'data' => $tukarShift,
                'status' => 'Reject',
                'pemohon' => true,
                'description' => $description
            ];
            app('App\Http\Controllers\EmailController')->index($data);
            $data = [
                'email' => $email_delegate,
                'data' => $tukarShift,
                'status' => 'Reject',
                'pemohon' => true,
                'description' => $description
            ];
            app('App\Http\Controllers\EmailController')->index($data);
        } else if ($approve) {
            DB::table('switch_permissions')
                ->where('id', $id)
                ->update(['next_approver' => $next_approver]);

            $email = User::where("id", $tukarShift->pemohon)->first()->email;
            $email_delegate = User::where("id", $tukarShift->delegate)->first()->email;

            $pemohon = User::select('name')->where('id', $tukarShift->pemohon)->get();
            $tukarShift->pemohon = (count($pemohon) > 0) ? $pemohon[0]->name : "";
            $delegate = User::select('name')->where('id', $tukarShift->delegate)->get();
            $tukarShift->delegate = (count($delegate) > 0) ? $delegate[0]->name : "";
            $next = User::select('name')->where('id', $tukarShift->next_approver)->get();
            $tukarShift->next_approver = (count($next) > 0) ? $next[0]->name : "";

            $data = [
                'email' => $email,
                'data' => $tukarShift,
                'status' => 'Approve',
                'next_approver' => $next_approver,
                'pemohon' => true,
                'description' => $description
            ];

            app('App\Http\Controllers\EmailController')->index($data);


            $data = [
                'email' => $email_delegate,
                'data' => $tukarShift,
                'status' => 'Approve',
                'next_approver' => $next_approver,
                'pemohon' => true,
                'description' => $description
            ];

            app('App\Http\Controllers\EmailController')->index($data);


            $email = User::where("id", $next_approver)->first()->email;

            $data = [
                'email' => $email,
                'data' => $tukarShift,
                'status' => 'Approve',
                'next_approver' => $next_approver,
                'pemohon' => false,
                'description' => $description
            ];
            app('App\Http\Controllers\EmailController')->index($data);
        }
        return response()->json(["Success" => true]);
    }
}
