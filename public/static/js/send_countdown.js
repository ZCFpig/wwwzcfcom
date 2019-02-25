// 初始化获取验证码倒计时时间
var countDownNum = 60;
// 获取验证码倒计时方法
function countDown(e, timer) {
    countDownNum--;
    e.innerHTML = countDownNum + 'S';
    if (countDownNum <= 0) {
        e.removeAttribute('disabled');
        e.innerHTML = '重新获取';
        countDownNum = 60;
        clearInterval(timer);
    }
}