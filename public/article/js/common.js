;(function($){
    $.extend({
        // 实时获取1rem单位的px值 rem转像素$.(6.4) 输入出375
        getPxByRem:function(remSize){  
            var deviceWidth = document.body && document.body.clientWidth || document.getElementsByTagName("html")[0].offsetWidth;
            return remSize * (deviceWidth * 200 / 1280);
        },
        // 获取元素的带小数点位的值
        getRealStyle: function (element, attr) {
            var getValue = element.currentStyle ? element.currentStyle[attr] : window.getComputedStyle(element, null)[attr];
            return parseFloat(getValue);
        },
        // 添加事件绑定
        addEventHandler: function (element, type, handler) {
            if (element.addEventListener) {
                element.addEventListener(type, handler, false);
            }else if (element.attachEvent) {
                element.attachEvent('on' + type, handler);
            }else{
                element['on' + type] = handler;
            }
        }
    });
})(jQuery);

// 实时获取1rem单位的px值 rem转像素
function remChangePx (remSize) {
    var deviceWidth = document.body && document.body.clientWidth || document.getElementsByTagName("html")[0].offsetWidth;
    return remSize * (deviceWidth * 200 / 1280);
}

$(function () {
    // 底部footer点击弹出下拉菜单
    !function () {
        // 点击底部导航
        $('.m-footer-nav>li>a').on('click', function (e) {
            e.stopPropagation();
            $(this).parent().siblings().find('.m-footer-navlist').hide();
            $(this).next().toggle();
        });

        // 点击底部下拉菜单列表
        $('.m-footer-navlist').find('li').on('click', function () {
            $(this).parent().hide();
        });
        // 点击body 隐藏下拉菜单
        $('body').on('click', function () {

            $('.m-footer-navlist').hide();
        });
    }();    
});


