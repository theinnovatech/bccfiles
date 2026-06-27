<template>
    <UiCard>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="shadcn-card-title">Employees</h3>
                    <p class="shadcn-card-description">
                        Manage employee records. Department Users receive login
                        credentials by email.
                    </p>
                </div>
                <UiButton @click="openDialog()">
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Employee
                </UiButton>
            </div>
        </template>

        <TableFilters
            v-model="filters"
            :filters="filterConfig"
            :has-active-filters="hasActiveFilters"
            :result-count="filteredEmployees.length"
            @reset="resetFilters"
        />

        <div class="obims-table-wrap">
            <DataTable
                :value="filteredEmployees"
                :loading="loading"
                paginator
                :rows="10"
                class="rounded-md border border-[#c8d6ef]"
            >
                <Column field="employee_number" header="Employee ID" />
                <Column field="name" header="Name" />
                <Column field="position" header="Position">
                    <template #body="{ data }">{{
                        data.position || "—"
                    }}</template>
                </Column>
                <Column header="Department">
                    <template #body="{ data }">{{
                        data.department?.name || "—"
                    }}</template>
                </Column>
                <Column header="Role">
                    <template #body="{ data }">
                        <span
                            class="stock-count-badge"
                            :class="roleBadgeClass(data.role)"
                            >{{ roleLabel(data.role) }}</span
                        >
                    </template>
                </Column>
                <Column field="contact_email" header="Contact Email" />
                <Column header="User Account">
                    <template #body="{ data }">
                        <span
                            class="stock-count-badge"
                            :class="
                                data.user
                                    ? 'stock-count-badge-success'
                                    : 'stock-count-badge-muted'
                            "
                        >
                            {{ data.user ? "Linked" : "None" }}
                        </span>
                    </template>
                </Column>
                <Column header="Actions" style="width: 6rem">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <UiButton
                                variant="ghost"
                                size="icon"
                                @click="openDialog(data)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"
                                    />
                                </svg>
                            </UiButton>
                            <UiButton
                                variant="ghost"
                                size="icon"
                                @click="remove(data)"
                            >
                                <svg
                                    class="h-4 w-4 text-[#ce1126]"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                    />
                                </svg>
                            </UiButton>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="editingId ? 'Edit Employee' : 'Add Employee'"
            :style="{ width: '520px' }"
        >
            <div class="space-y-4 pt-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Full Name
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <InputText
                            v-model="form.name"
                            class="w-full"
                            placeholder="e.g. Maria Santos"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Contact Email
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <InputText
                            v-model="form.contact_email"
                            type="email"
                            class="w-full"
                            placeholder="employee@email.com"
                        />
                        <p class="mt-1 text-xs text-[#5b7fbf]">
                            Used as login email if role is Department User.
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Department
                            <span class="text-[#ce1126]">*</span></label
                        >
                        <Select
                            v-model="form.department_id"
                            :options="departments"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select department"
                            class="w-full"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Role <span class="text-[#ce1126]">*</span></label
                        >
                        <Select
                            v-model="form.role"
                            :options="roles"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select role"
                            class="w-full"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            class="mb-1 block text-sm font-medium text-[#002a7a]"
                            >Position</label
                        >
                        <InputText
                            v-model="form.position"
                            class="w-full"
                            placeholder="e.g. Clerk, Teacher, Coordinator"
                        />
                    </div>
                </div>

                <div
                    v-if="form.role === 'department_user' && !editingId"
                    class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm"
                >
                    <div class="flex items-start gap-2">
                        <svg
                            class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p class="text-blue-800">
                            A login account will be created automatically and
                            credentials will be sent to
                            <strong>{{
                                form.contact_email || "the contact email"
                            }}</strong
                            >.
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        form.role === 'department_user' && editingId && !hasUser
                    "
                    class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm"
                >
                    <div class="flex items-start gap-2">
                        <svg
                            class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p class="text-amber-800">
                            No user account found. Saving will create one and
                            send credentials to the contact email.
                        </p>
                    </div>
                </div>
            </div>

            <template #footer>
                <UiButton variant="outline" @click="dialogVisible = false"
                    >Cancel</UiButton
                >
                <UiButton :loading="saving" @click="save">
                    {{ editingId ? "Save Changes" : "Create Employee" }}
                </UiButton>
            </template>
        </Dialog>
    </UiCard>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import TableFilters from "../../components/TableFilters.vue";
import { confirmDelete } from "../../composables/confirm";
import { useNotify } from "../../composables/useNotify";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";

const notify = useNotify();
const employees = ref([]);
const departments = ref([]);
const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const hasUser = ref(false);

const form = reactive({
    name: "",
    contact_email: "",
    department_id: null,
    role: "department_user",
    position: "",
});

const roles = [
    { label: "Admin", value: "admin" },
    { label: "Supply Officer", value: "supply_officer" },
    { label: "Department User", value: "department_user" },
];

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Name, email, position, department...",
        fields: [
            "name",
            "contact_email",
            "position",
            "employee_number",
            "department.name",
        ],
    },
    {
        key: "role",
        type: "select",
        label: "Role",
        field: "role",
        options: [{ label: "All roles", value: "" }, ...roles],
    },
]);

const {
    filters,
    filteredItems: filteredEmployees,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(employees, filterConfig);

function roleLabel(role) {
    return roles.find((entry) => entry.value === role)?.label || role || "—";
}

function roleBadgeClass(role) {
    if (role === "admin") return "stock-count-badge-active";
    if (role === "supply_officer") return "stock-count-badge-complete";
    return "";
}

function openDialog(employee = null) {
    editingId.value = employee?.id ?? null;
    hasUser.value = !!employee?.user;
    form.name = employee?.name ?? "";
    form.contact_email = employee?.contact_email ?? "";
    form.department_id = employee?.department_id ?? null;
    form.role = employee?.role ?? "department_user";
    form.position = employee?.position ?? "";
    dialogVisible.value = true;
}

async function save() {
    if (
        !form.name ||
        !form.contact_email ||
        !form.department_id ||
        !form.role
    ) {
        notify.warn("Please fill in all required fields.");
        return;
    }

    saving.value = true;
    try {
        if (editingId.value) {
            const { data } = await api.put(
                `/employees/${editingId.value}`,
                form,
            );
            if (data.mail_sent) {
                notify.success(
                    data.message || "Employee updated successfully.",
                );
            } else {
                notify.warn(
                    data.message ||
                        "Employee updated, but credentials email could not be sent.",
                );
            }
        } else {
            const { data } = await api.post("/employees", form);
            if (data.mail_sent) {
                notify.success(
                    data.message || "Employee created successfully.",
                    "Employee created",
                );
            } else {
                notify.warn(
                    data.message ||
                        "Employee created, but credentials email could not be sent.",
                );
            }
        }
        dialogVisible.value = false;
        await load();
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to save employee.",
        );
    } finally {
        saving.value = false;
    }
}

function remove(employee) {
    confirmDelete({
        title: "Delete employee?",
        message: `Remove "${employee.name}" from the system?`,
        detail: "The employee will be moved to Deleted Data and can be restored later.",
        onAccept: async () => {
            try {
                await api.delete(`/employees/${employee.id}`);
                await load();
                notify.success("Employee deleted.");
            } catch (error) {
                notify.error(
                    error.response?.data?.message ||
                        "Unable to delete employee.",
                );
                throw error;
            }
        },
    });
}

async function load() {
    loading.value = true;
    try {
        const [empRes, deptRes] = await Promise.all([
            api.get("/employees/list"),
            api.get("/departments/list"),
        ]);
        employees.value = empRes.data.data ?? empRes.data;
        departments.value = deptRes.data.data ?? deptRes.data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load employees.",
        );
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
