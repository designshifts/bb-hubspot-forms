export const pushFormInteraction = ({ event_type, message, field_name } = {}) => {
    if (!event_type || !message) {
        console.warn("pushFormInteraction requires event_type and message");
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: "form_interaction",
        event_type,
        message,
        ...(field_name && { field_name })
    }); 
}