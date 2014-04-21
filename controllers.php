<?php
use Symfony\Component\HttpFoundation\Request;

// Helpers
function sms2display(&$sms){
    $status_codes = [STATUS_SENDING => 'sending',
                     STATUS_SENT => 'sent',
                     STATUS_RECIEVED => 'received',
                     STATUS_SEND_FAIL => 'send failed'];
    $sms['status'] = $status_codes[$sms['status']];
    $sms['created_at'] = date('c', $sms['created_at']);
    unset($sms['user_id']);
}

function user2display(&$user){
    $user['roles'] = explode(',', $user['roles']);
    $user['created_at'] = date('Y-m-d H:i:s', $user['created_at']);
    unset($user['password']);
}

function fetch($query, $params) {
    global $app;
    return $app['db']->executeQuery($query, $params)->fetch(PDO::FETCH_OBJ);
}

function get_user($app){
    $token = $app['security']->getToken();
    if (null === $token) throw new Exception('user not found');
    return $token->getUser();
}

function encode_password($raw_password){
    global $app;
    $user = get_user($app);
    $encoder = $app['security.encoder_factory']->getEncoder($user);
    return $encoder->encodePassword($raw_password, $user->getSalt());
}

$validate_api = function (Request $request) use ($app) {
    global $json_as_post_params;
    $json_as_post_params($request);
    $api_key = trim($request->headers->get('Api-Key'));
    if ($api_key) {
        // Validates the api key
        $user = fetch("SELECT * FROM user WHERE token=?", [$api_key]);  // TODO rename the field to api_key
        $user->roles = explode(',', $user->roles);
        if (empty($user)) throw new Exception('invalid api_key: '.$api_key);
        $app['current_user'] = $user;
        return;
    }
    throw new Exception('invalid request');
};

$json_as_post_params = function(Request $request) use ($app){
    $is_json = 0 === strpos($request->headers->get('Content-Type'), 'application/json');
    if ($is_json) {
        // Replaces request data with json data
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
};

function requires_role($role){
    global $app;
    return function (Request $request) use ($app, $role) {
        if (!in_array($role, $app['current_user']->roles)) {
            throw new Exception('permission denied');
        }
    };
}


// Frontend pages
$app->get('/admin/', function() use($app) {
    return $app['twig']->render('admin.twig.html', []);
});

$app->get('/admin/user_list/', function() use($app){
    $users = $app['db']->fetchAll("SELECT * FROM user");
    foreach ($users as &$user) {
        user2display($user);
    }
    return $app->json($users);
});

$app->post('/admin/user_save/', function(Request $r) use($app){
    // TODO validate values
    $id = (int)$r->request->get('id');
    $user = fetch("SELECT * FROM user WHERE id=?", [$id]);

    $values = ['name' => $r->request->get('name'),
               'token' => $r->request->get('token'),
               'roles' => implode(',', $r->request->get('roles')),
               'phone' => $r->request->get('phone')];
    if ($r->request->get('password')) {
        $values['password'] = encode_password($r->request->get('password'));
    }
    if ($user) {
        $app['db']->update('user', $values, ['id' => $id]);
    }else{
        $values['created_at'] = time();
        $app['db']->insert('user', $values);
        $id = $app['db']->lastInsertId();
    }
    $user = $app['db']->fetchAssoc("SELECT * FROM user WHERE id=?", [$id]);
    user2display($user);
    return $app->json(['msg' => 'success', 'user' => $user]);
})->before($json_as_post_params);

$app->get('/admin/user_delete/', function(Request $r) use ($app){
    $id = (int)$r->query->get('id');
    $user = fetch("SELECT * FROM user WHERE id=?", [$id]);
    if ($user) {
        $app['db']->delete('user', ['id' => $id]);
        return $app->json(['msg' => 'success']);
    }else{
        return $app->json(['msg' => 'user not found']);
    }
});

$app->get('/admin/sms_list/', function(Request $r) use ($app){
    $sms_list = $app['db']->fetchAll("SELECT * FROM sms ORDER BY created_at DESC");
    foreach ($sms_list as &$sms) {
        sms2display($sms);
    }
    return $app->json($sms_list);
});

// API functions for api users
$app->post('/send/', function(Request $r) use($app) {
    /**
     * Sends new sms to the given number.
     * Requires following arguments as json:
     * * body  - Message body, up to 250 chars
     * * phone - Phone number, 8 chars
     */
    $cur_user = $app['current_user'];
    $body_is_valid = preg_match('/^[A-z_-\d\s]{1,250}$/', $r->request->get('body'), $body);
    $phone_is_valid = preg_match('/^[\d]{8}$/', $r->request->get('phone'), $phone);
    if ($phone_is_valid && $body_is_valid) {
        $sms = ['phone' => $phone[0],
                'body' => $body[0],
                'user_id' => $cur_user->id,
                'status' => STATUS_SENDING,
                'created_at' => time()];
        $app['db']->insert('sms', $sms);
        $sms['id'] = $app['db']->lastInsertId();
        sms2display($sms);
        return $app->json($sms, 201);
    }else{
        $errors = [];
        if (!$phone_is_valid) {
            $errors['phone'] = 'Phone number is required. 8 digit phone number is allowed.';
        }
        if (!$body_is_valid) {
            $errors['body'] = 'SMS body is required. Up to 250 characters with alphanumeric, space, underscore and '
                            . 'dash characters are allowed.';
        }
        $rval = $r->request->all();
        $rval['errors'] = &$errors;
        return $app->json($rval, 400);
    }
})->before($validate_api)->before(requires_role('ROLE_API'));

$app->get('/list_received/', function(Request $r) use($app) {
    /**
     * Lists received sms according to following querystring params:
     * * date_from - Date from in YYYY-MM-DD HH:MM:SS format.
     * * date_to   - Date to in YYYY-MM-DD HH:MM:SS format.
     */
    $filters = 'user_id=? AND status=?';
    $values = [$app['current_user']->id, STATUS_RECIEVED];

    if ($r->query->has('date_from')) {
        $filters .= ' AND created_at>=?';
        $values[] = strtotime($r->query->get('date_from'));
    }
    if ($r->query->has('date_to')) {
        $filters .= ' AND created_at<=?';
        $values[] = strtotime($r->query->get('date_to'));
    }
    $sms_list = $app['db']->fetchAll("SELECT * FROM sms WHERE $filters", $values);
    foreach ($sms_list as &$sms) {
        sms2display($sms);
    }
    return $app->json($sms_list, 200);
})->before($validate_api)->before(requires_role('ROLE_API'));

// API functions for system
$app->get('/pending/', function (Request $r) use($app) {
    /**
     * Get next sms to send by supplying following parameter as querystring:
     * * last_id - Last sms id, so that it knows the next sms
     */
    $last_id = (int)$r->query->get('last_id', 0);
    $sql = 'SELECT * FROM sms WHERE id>? AND status=? ORDER BY created_at ASC, id ASC';
    $sms = $app['db']->fetchAssoc($sql, [$last_id, STATUS_SENDING]);
    if ($sms) {
        sms2display($sms);
        return $app->json($sms, 200);
    }else{
        $rsp = ['id' => $last_id, 'errors' => ['id' => 'No pending SMS']];
        return $app->json($rsp, 200);
    }
})->before($validate_api)->before(requires_role('ROLE_SYSTEM'));

$app->post('/sent/', function (Request $r) use($app) {
    /**
     * Notify that pending sms has been processed. Requires following as json:
     * * id - SMS id that has been sent
     * * sent - 1 if sent, 0 if failed.
     */
    $id = (int)$r->request->get('id');
    $sms = fetch('SELECT * FROM sms WHERE id=? AND status=?', [$id, STATUS_SENDING]);
    if ($sms) {
        $status = ($r->request->get('sent') == 1 ? STATUS_SENT : STATUS_SEND_FAIL);
        $app['db']->update('sms', ['status' => $status], ['id' => $sms->id]);
        $sms = $app['db']->fetchAssoc('SELECT * FROM sms WHERE id=?', [$sms->id]);
        sms2display($sms);
        return $app->json($sms, 200);
    }else{
        $rsp = ['id' => $id, 'errors' => ['id' => 'Please specify correct SMS id']];
        return $app->json($rsp, 400);
    }
    $rsp = [];
})->before($validate_api)->before(requires_role('ROLE_SYSTEM'));

$app->post('/sms_received/', function (Request $r) use($app) {
    /**
     * Notify about received sms. Requires following json params:
     * * body  - Message body
     * * phone - Phone number
     */
    $sms = ['phone' => $r->request->get('phone'),
            'body' => $r->request->get('body'),
            'user_id' => $app['current_user']->id,
            'status' => STATUS_RECIEVED,
            'created_at' => time()];
    $app['db']->insert('sms', $sms);
    $sms['id'] = $app['db']->lastInsertId();
    sms2display($sms);
    return $app->json($sms, 201);
})->before($validate_api)->before(requires_role('ROLE_SYSTEM'));


// vim: set fdm=marker tw=120 fmr={,} :
