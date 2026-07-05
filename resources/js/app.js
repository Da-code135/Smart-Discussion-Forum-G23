document.addEventListener("click", (event) => {
    document.querySelectorAll("[data-user-menu]").forEach((menu) => {
        const trigger = menu.querySelector("[data-menu-toggle]");
        const clickedInside = menu.contains(event.target);

        if (trigger && trigger.contains(event.target)) {
            menu.classList.toggle("is-open");
            return;
        }

        if (!clickedInside) {
            menu.classList.remove("is-open");
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        document.querySelectorAll("[data-user-menu]").forEach((menu) => {
            menu.classList.remove("is-open");
        });
    }
});
