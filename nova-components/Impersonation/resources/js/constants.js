let BACKEND_URL = "";
switch (process.env.NODE_ENV) {
    case "production":
        BACKEND_URL = "https://backend.trytrig.com";
        break;
    case "development":
        BACKEND_URL = "http://backend.test";
        break;
    default:
        BACKEND_URL = "https://backend.trytrig.com";
}

export { BACKEND_URL };
