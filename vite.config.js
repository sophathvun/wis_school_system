import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/academicYears.js",
                "resources/js/grade.js",
                "resources/js/class.js",
                "resources/js/session.js",
                "resources/js/schoolProfile.js",
                "resources/js/educationLevel.js",
                "resources/js/program.js",
                "resources/js/group.js",
                "resources/js/studentEnrollment.js",
                "resources/js/locations.js",
                "resources/js/families.js",
                "resources/js/occupation.js",
                "resources/js/nationality.js",
                "resources/js/brandingSettings.js",
                "resources/js/userManagement.js",
                "resources/js/profilePhoto.js",
            ],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
