"use strict";

angular.module('sms', ['ngResource'])

.config(function($interpolateProvider){  //{{{1
    $interpolateProvider.startSymbol('((').endSymbol('))');
})

.factory('User', function($resource){  //{{{1
    return $resource('/admin/:action/', {}, {
        list: {method: 'GET',  params: {action: 'user_list'}, isArray: true},
        remove: {method: 'DELETE', params: {action: 'user_delete'}, isArray: false}
    });
})

.controller('MainController', function($scope, $http, User){  //{{{1
    $scope.edit_user = {};
    $scope.users = User.list();
    $scope.message = '';

    $scope.edit = function(u){ $scope.edit_user = angular.copy(u); }
    $scope.reset_form = function(){ $scope.edit_user = {}; }
    $scope.save = function(u){  // {{{
        var user = new User(u);
        $http.post('/admin/user_save/', angular.copy(user)).success(function(rsp){
            $scope.message = rsp.msg;
            $scope.edit_user = rsp.user;
            $scope.users = User.list();
        });
    }  // }}}
    $scope.remove = function(u){  // {{{
        $http.get('/admin/user_delete/?id=' + u.id).success(function(rsp){
            $scope.message = rsp.msg;
            $scope.edit_user = {};
            $scope.users = User.list();
        });
    }  // }}}
})

;


// vim: fdm=marker
