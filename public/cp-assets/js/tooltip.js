//Tooltip Plugins
function initTooltip() {
    const allTooltipButtons = document.querySelectorAll('[data-tooltip]');

    allTooltipButtons.forEach(element => {
        const tooltipContent = element.getAttribute('data-tooltip-content');

        const tippyOptions = {
            content: tooltipContent,
        };

        if (element.getAttribute('data-tooltip-placement'))
            tippyOptions.placement = element.getAttribute('data-tooltip-placement');
        if (element.getAttribute('data-tooltip-content'))
            tippyOptions.content = element.getAttribute('data-tooltip-content');
        if (element.getAttribute('data-tooltip-arrow'))
            tippyOptions.arrow = element.getAttribute('data-tooltip-arrow') === "false" ? false : element.getAttribute('data-tooltip-arrow') === "true" ? true : element.getAttribute('data-tooltip-arrow');
        if (element.getAttribute('data-tooltip-duration'))
            tippyOptions.duration = element.getAttribute('data-tooltip-duration');
        if (element.getAttribute('data-tooltip-animation'))
            tippyOptions.animation = element.getAttribute('data-tooltip-animation');
        if (element.getAttribute('data-tooltip-trigger'))
            tippyOptions.trigger = element.getAttribute('data-tooltip-trigger');
        if (element.getAttribute('data-tooltip-follow-cursor'))
            tippyOptions.followCursor = element.getAttribute('data-tooltip-follow-cursor');
        if (element.getAttribute('data-tooltip-theme'))
            tippyOptions.theme = element.getAttribute('data-tooltip-theme');

        tippy(element, tippyOptions);
    });
}

function init() {
    initTooltip();
}
init();