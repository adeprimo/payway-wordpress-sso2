var prodListController = angular.module('Tulo.Admin.ProductListController', []);
prodListController.controller('ProductListController', ['$scope', '$http', function ($scope, $http) {

        $scope.model = {};
        $http.get(ajaxurl + '?action=tulo_getproducts').success(function (data) {
            $scope.model.Products = data;
        });

        $scope.addProduct = function () {
            $scope.model.Products.push({});
            return false;
        };

        $scope.delete = function (item) {
            var index = $scope.model.Products.indexOf(item);
            $scope.model.Products.splice(index, 1);
        };
        $scope.insertShortcode = function (e, item, shortcodeval) {
            
            var clickedButton = jQuery(e.target);
            var shortcode = clickedButton.text();
            
                
            var buyinfoArea = clickedButton.closest('.tulo_product_row').find('textarea.buy-info');
            if (shortcodeval)
                buyinfoArea.insertAtCaret(shortcode, '[/' + shortcodeval + ']');
            else
                buyinfoArea.insertAtCaret(shortcode);
            item.buyinfo = buyinfoArea.val();
            return false;
        };
    }]);