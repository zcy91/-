// 初始化播放器 PC端及移动端传入的宽度不同
function livePlayer (videoHeight, url) {
    // 初始化播放器
    var player = new prismplayer({
        id: "J_prismPlayer", // 容器id
        source: url,
        width: "100%",         // 播放器宽度
        height: videoHeight,      // 播放器高度
        autoplay: true
    });

};

// 倒计时 自动切换显示倒计时或播放器
function countDown (startJson) {
    var days, preDays, hours, preHours, minutes, preMinutes, seconds, preSeconds;
    var daysDom = $('.dates'), hoursDom = $('.hours'), minutesDom = $('.minutes'), secondsDom = $('.seconds');
    // 初始化倒计时
    fnInterval();

    clearInterval(timer);
    var timer = setInterval(fnInterval, 1000);

    function fnInterval () {
        var startTime = new Date(startJson.year, startJson.month-1, startJson.date, startJson.hour, startJson.minutes, startJson.seconds);
        var nowTime = new Date();
        var leftTime = startTime - nowTime;
        leftTime = leftTime < 0 ? 0: leftTime;

        if (leftTime === 0) {
            clearInterval(timer);
            // 隐藏倒计时 显示播放页面
            $('.count-down-wrapper').hide();
            $('.video-wrapper').show();
            return;
        }

        days = addZero(parseInt(leftTime / 1000 / 60 / 60 / 24 , 10)); // 剩余的天数 
        hours = addZero(parseInt(leftTime / 1000 / 60 / 60 % 24 , 10)); // 剩余的小时 
        minutes = addZero(parseInt(leftTime / 1000 / 60 % 60, 10)); // 剩余的分钟 
        seconds = addZero(parseInt(leftTime / 1000 % 60, 10)); // 剩余的秒数

        // 改变倒计时值
        if (days !== preDays) {
            daysDom.html(days);
        }
        if (hours !== preHours) {
            hoursDom.html(hours);
        }
        if (minutes !== preMinutes) {
            minutesDom.html(minutes);
        }
        secondsDom.html(seconds);
        
        // 记录这次的值
        preDays = days;
        preHours = hours;
        preMinutes = minutes;
        preSeconds = seconds;
    }

    // 将0-9的数字前面加上0 例如1变为01
    function addZero(i){  
      return i < 10 ? "0" + i: i;
    } 
    
};
