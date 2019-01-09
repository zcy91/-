// 根据不同设备设置body的font-size值
!function () {
    // 设置默认最大宽度
    var DEFAULT_MAX_WIDTH = 1280;
    // 设置默认最小宽度
    var DEFAULT_MIN_WIDTH = 320;
    //默认字体大小
    var DEFAULT_FONT_SIZE = 200;
    curFontSize();
    window.addEventListener('resize', curFontSize, false);

    // 实时获取设备的宽度 并设置font-size的值
    function curFontSize () {
        var deviceWidth = document.body && document.body.clientWidth || document.getElementsByTagName("html")[0].offsetWidth;
        deviceWidth = deviceWidth > 1280? 1280: deviceWidth < 320? 320: deviceWidth;
        var fontSize = DEFAULT_FONT_SIZE * deviceWidth / DEFAULT_MAX_WIDTH;
        document.getElementsByTagName("html")[0].setAttribute("style", "font-size:" + fontSize + "px !important");
    }
}();

