$(function () {
    $('[data-toggle="tooltip"]').tooltip()
});

var dmp = new diff_match_patch();
function do_diff(online_id, test_id, display_id) {
    var target = document.getElementById(display_id);
    if (target.innerHTML) {
        return;
    }
    var online = document.getElementById(online_id).innerHTML;
    var test = document.getElementById(test_id).innerHTML;
    var d = dmp.diff_main(online, test);
    dmp.diff_cleanupEfficiency(d);
    target.innerHTML = dmp.diff_prettyHtml(d);
}
