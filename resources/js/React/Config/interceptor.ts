import axios from "axios";

const baseURL = window.location.protocol + "//" + window.location.host;
const CSRFElement = document.querySelector('[name="csrf-token"]');
if (!CSRFElement) {
    throw new Error("CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token");
}
const csrfToken = CSRFElement.getAttribute("content");

const apiClient = axios.create({
    baseURL: baseURL,
    headers: {
        "x-csrf-token": csrfToken || "",
        "Content-type": "application/json",
    },
    timeout: 5000, // 5 seconds
});

// Add error handling
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response) {
            window.console.error(`API Error: ${error.response.status} - ${error.response.statusText}`);
        } else if (error.request) {
            window.console.error("API Error: No response received");
        } else {
            window.console.error("API Error:", error.message);
        }
        return Promise.reject(error);
    }
);

export default apiClient;
