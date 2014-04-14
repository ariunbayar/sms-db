"use strict";

angular.module('sms', ['ngResource'])

.config(function($interpolateProvider){
    $interpolateProvider.startSymbol('((').endSymbol('))');
})

.factory('User', function($resource){
    return $resource('/admin/:action/', {}, {
        list: {method: 'GET',  params: {action: 'user_list'}, isArray: true},
        remove: {method: 'DELETE', params: {action: 'user_delete'}, isArray: false}
    });
})

.factory('SMS', function($resource){
    return $resource('/admin/:action/', {}, {
        list: {method: 'GET',  params: {action: 'sms_list'}, isArray: true},
    });
})

.controller('MainController', function($scope, $http, User, SMS){
    $scope.edit_user = {};
    $scope.users = User.list();
    $scope.message = '';
    $scope.demo_values = {
        api_key: '1234567890',
        phone: '12345678',
    };
    $scope.sms_list = SMS.list();

    $scope.edit = function(u){ $scope.edit_user = angular.copy(u); }
    $scope.reset_form = function(){ $scope.edit_user = {}; }
    $scope.save = function(u){
        var user = new User(u);
        $http.post('/admin/user_save/', angular.copy(user)).success(function(rsp){
            $scope.message = rsp.msg;
            $scope.edit_user = rsp.user;
            $scope.users = User.list();
        });
    }
    $scope.remove = function(u){
        $http.get('/admin/user_delete/?id=' + u.id).success(function(rsp){
            $scope.message = rsp.msg;
            $scope.edit_user = {};
            $scope.users = User.list();
        });
    }
    $scope.test_api = function(url){
        $scope.demo_values.url = url;
    }
    $scope.test_request = function(){
        var config = {url: $scope.demo_values.url, params: {},
                      headers: {'Api-Key': $scope.demo_values.api_key}};
        var request = {};
        if ($scope.demo_values.url == '/send/') {
            config.method = 'POST';
            config.data = {body: $scope.demo_values.body,
                           phone: $scope.demo_values.phone};
        }else if ($scope.demo_values.url == '/list_received/') {
            config.method = 'GET';
            config.params = {date_from: $scope.demo_values.date_from,
                             date_to: $scope.demo_values.date_to};
        }else if ($scope.demo_values.url == '/pending/') {
            config.method = 'GET';
            config.params = {last_id: $scope.demo_values.last_id};
        }else if ($scope.demo_values.url == '/sent/') {
            config.method = 'POST';
            config.data = {id: $scope.demo_values.id};
        }else if ($scope.demo_values.url == '/sms_received/') {
            config.method = 'POST';
            config.data = {body: $scope.demo_values.body,
                           phone: $scope.demo_values.phone};
        }else{
            return;
        }

        config.params._ = new Date().getTime();
        $http(config).success(function(rsp){
            $scope.demo_output = rsp;
        }).error(function(rsp){
            $scope.demo_output = rsp;
        });
    }
})

;

// vim: set fdm=marker fmr={,} :
