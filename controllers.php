<?php
use Symfony\Component\HttpFoundation\Request;

// Helpers  {{{1
function sms2display(&$sms){  // {{{2
    $status_codes = [STATUS_SENDING => 'sending',
                     STATUS_SENT => 'sent',
                     STATUS_RECIEVED => 'recieved'];
    $sms['status'] = $status_codes[$sms['status']];
    $sms['created_at'] = date('Y-m-d H:i:s', $sms['created_at']);
    unset($sms['user_id']);
}

function user2display(&$user){  // {{{2
    $user['roles'] = explode(',', $user['roles']);
    $user['created_at'] = date('Y-m-d H:i:s', $user['created_at']);
    unset($user['password']);
}

function fetch($query, $params) {  // {{{2
    global $app;
    return $app['db']->executeQuery($query, $params)->fetch(PDO::FETCH_OBJ);
}

function encode_password($raw_password){  // {{{2
    global $app;
    $token = $app['security']->getToken();
    if (null === $token) throw new Exception('user not found');
    $user = $token->getUser();
    $encoder = $app['security.encoder_factory']->getEncoder($user);
    return $encoder->encodePassword($raw_password, $user->getSalt());
}

$validate_api = function (Request $request) use ($app) {  // {{{2
    $validate_json($request);
    $api_key = trim($request->headers->get('Api-Key'));
    if ($api_key) {  // {{{3
        // Validates the api key
        $user = fetch("SELECT * FROM user WHERE token=?", [$api_key]);  // TODO rename the field to api_key
        if (empty($user)) throw new Exception('invalid api_key: '.$api_key);
        $app['current_user'] = $user;
        return;
    }  // }}}
    throw new Exception('invalid request');
};

$validate_json = function(Request $request) use ($app){  // {{{2
    $is_json = 0 === strpos($request->headers->get('Content-Type'), 'application/json');
    if ($is_json) {
        // Replaces request data with json data
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
        return;
    }
    throw new Exception('no json data supplied');
};


// Frontend pages {{{1
$app->get('/admin/', function() use($app) {  // {{{2
    return $app['twig']->render('admin.twig.html', []);
});

$app->get('/admin/user_list/', function() use($app){  // {{{2
    $users = $app['db']->fetchAll("SELECT * FROM user");
    foreach ($users as &$user) {
        user2display($user);
    }
    return $app->json($users);
});

$app->post('/admin/user_save/', function(Request $r) use($app){  // {{{2
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
})->before($validate_json);

$app->get('/admin/user_delete/', function(Request $r) use ($app){  // {{{2
    $id = (int)$r->query->get('id');
    $user = fetch("SELECT * FROM user WHERE id=?", [$id]);
    if ($user) {
        $app['db']->delete('user', ['id' => $id]);
        return $app->json(['msg' => 'success']);
    }else{
        return $app->json(['msg' => 'user not found']);
    }
});

// API functions {{{1
$app->post('/send/', function(Request $r) use($app) {  // {{{2
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
})->before($validate_api);

$app->post('/pending/{last_id}', function (Request $r) use($app) {  // {{{2
    $sms = [];
    return $app->json($sms, 200);
})->before($validate_api);

$app->get('/list_recieved/', function(Request $r) use($app) {  // {{{2
    /**
     * Lists recieved sms according to following querystring params:
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
})->before($validate_api);


// vim: fdm=marker tw=120
