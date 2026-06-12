document.addEventListener("DOMContentLoaded", function() {
    if (window.Echo) {
        const channel = window.Echo.channel("moonshine-notifications");
        
        channel.listen(".notification.sent", (e) => {
            if (typeof MoonShine !== 'undefined' && MoonShine.ui && MoonShine.ui.toast) {
                MoonShine.ui.toast(e.message, e.color || "info");
            }
        });

        channel.listen(".table.updated", (e) => {
            console.log(`[Echo] Table update requested for: ${e.tableName}`);

            // Dispatch all possible variants for maximum compatibility with MoonShine 4
            const events = [
                `table_updated:${e.tableName}`,
                `table-updated:${e.tableName}`,
                `fragment_updated:crud-list`,
                `fragment-updated:crud-list`
            ];

            events.forEach(eventName => {
                window.dispatchEvent(new CustomEvent(eventName));
            });
        });
    }
});