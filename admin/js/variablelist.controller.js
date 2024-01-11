var variableListController = angular.module('Tulo.Admin.VariableListController', []);
variableListController.controller('VariableListController', ['$scope', '$http', function ($scope, $http) {

        $scope.model = {};
        $http.get(ajaxurl + '?action=tulo_getvariables').success(function (data) {
            $scope.model.Variables = data;
        });

        $scope.addVariable = function () {
            $scope.model.Variables.push({});
            return false;
        };

        $scope.deleteVariable = function (item) {
            var index = $scope.model.Variables.indexOf(item);
            $scope.model.Variables.splice(index, 1);
        };
        
    }]);