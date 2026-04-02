export const INDIAN_TIMEZONE = 'Asia/Kolkata';

export const formatIndianDate = (value, options = {}) => {
    if (!value) return '';

    return new Intl.DateTimeFormat('en-IN', {
        timeZone: INDIAN_TIMEZONE,
        ...options,
    }).format(new Date(value));
};

export const formatIndianDateTime = (
    value,
    dateOptions = { day: 'numeric', month: 'short' },
    timeOptions = { hour: '2-digit', minute: '2-digit' },
) => {
    if (!value) return '';

    return `${formatIndianDate(value, dateOptions)} ${new Date(value).toLocaleTimeString('en-IN', {
        timeZone: INDIAN_TIMEZONE,
        ...timeOptions,
    })}`;
};

export const todayIndianDate = () => {
    return toIndianDateInput(new Date());
};

export const toIndianDateInput = (value) => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: INDIAN_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date(value));

    const get = (type) => parts.find((part) => part.type === type)?.value;

    return `${get('year')}-${get('month')}-${get('day')}`;
};
