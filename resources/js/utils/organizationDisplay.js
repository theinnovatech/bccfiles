export function updateOrganizationNameDisplay(name) {
    const element = document.getElementById('app-organization-name');

    if (element && name) {
        element.textContent = name;
    }
}
