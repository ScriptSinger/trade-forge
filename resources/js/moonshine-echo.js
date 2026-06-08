document.addEventListener("DOMContentLoaded", function() {
    if (window.Echo) {
        window.Echo.channel("moonshine-notifications")
            .listen(".notification.sent", (e) => {
                if (typeof MoonShine !== 'undefined' && MoonShine.ui && MoonShine.ui.toast) {
                    MoonShine.ui.toast(e.message, e.color || "info");
                }
            });
    }
});