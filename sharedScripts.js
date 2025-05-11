let myBtn = document.getElementById('toTopBtn');

window.onscroll = function () {
    scrollFunc();
};

function scrollFunc() {
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        myBtn.style.display = "block";
    } else {
        myBtn.style.display = "none";
    }
}

function toTop() {
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
}
