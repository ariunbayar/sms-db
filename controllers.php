<?php
use Symfony\Component\HttpFoundation\Request;

$app->get('/{id}', function($id) use($app) {  // {{{1
    $sql = "SELECT * FROM user WHERE id=?";
    $user = $app['db']->fetchAssoc($sql, array((int)$id));
    return $user['id'] . $user['name'];
});

/* API functions */
$app->post('/{id}', function(Request $r) use($app) {  // {{{1
    $rsp_data = [
        'p1' => $r->request->get('title'),
        'p2' => $r->request->get('body'),
    ];
    return $app->json($rsp_data, 201);
});


// vim: fdm=marker
