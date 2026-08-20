<template>
    <div class="space-y-6">
        <div class="shadcn-card overflow-hidden">
            <div
                class="stock-op-hero border-b border-[#a8b8d4] px-4 py-5 sm:px-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#00164d]">
                            Record Issuance
                        </h3>
                        <p class="mt-1 text-sm text-[#4a6490]">
                            Choose items or equipments, then enter the issuance
                            details from the hard-copy form.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="tab in issuanceTypeTabs"
                        :key="tab.key"
                        type="button"
                        class="stock-op-tab-card"
                        :class="{
                            'stock-op-tab-card-active':
                                form.issuance_type === tab.key,
                        }"
                        @click="setIssuanceType(tab.key)"
                    >
                        <span class="stock-op-tab-icon" aria-hidden="true">
                            <component :is="tab.icon" />
                        </span>
                        <span class="stock-op-tab-label">{{ tab.label }}</span>
                        <span class="stock-op-tab-desc">{{
                            tab.description
                        }}</span>
                    </button>
                </div>
            </div>

            <form
                class="min-w-0 space-y-5 p-4 sm:p-6"
                @submit.prevent="submit"
            >
                <div class="border border-[#a8b8d4] bg-transparent p-4">
                    <div class="mb-4">
                        <h4 class="font-semibold text-[#00164d]">
                            {{
                                form.issuance_type === "items"
                                    ? "Items to Issue"
                                    : "Equipments to Issue"
                            }}
                        </h4>
                        <p class="mt-0.5 text-sm text-[#4a6490]">
                            Encode the hard-copy form here: who received it,
                            then the
                            {{
                                form.issuance_type === "items"
                                    ? "item"
                                    : "equipment"
                            }}
                            lines.
                        </p>
                    </div>

                    <div class="issuance-section">
                        <p class="issuance-section-label">Issuance details</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label
                                    class="mb-1 block text-sm font-medium text-[#00164d]"
                                >
                                    Reference No.
                                    <span
                                        class="ml-1 text-xs font-normal text-[#4a6490]"
                                        >(optional)</span
                                    >
                                </label>
                                <InputText
                                    v-model="form.issuance_number"
                                    class="w-full"
                                    placeholder="e.g. ISS-2024-018"
                                />
                                <p class="mt-1 text-xs text-[#4a6490]">
                                    Only fill this in if the hard-copy form has
                                    its own reference number. Otherwise the
                                    system generates one.
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-[#00164d]"
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
                                    filter
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-[#00164d]"
                                    >Received By
                                    <span class="text-[#ce1126]">*</span></label
                                >
                                <AutoComplete
                                    v-model="receivedByInput"
                                    :suggestions="receiverSuggestions"
                                    optionLabel="name"
                                    dropdown
                                    :forceSelection="false"
                                    placeholder="Select or type receiver name"
                                    class="w-full"
                                    inputClass="w-full"
                                    :disabled="!form.department_id"
                                    @complete="searchReceivers"
                                />
                                <p class="mt-1 text-xs text-[#4a6490]">
                                    Pick an employee or type a name if not in
                                    the list.
                                </p>
                            </div>
                            <div
                                class="md:col-span-2 space-y-3 rounded-md border border-[#a8b8d4] bg-[#f4f7fb] p-3"
                            >
                                <div class="flex items-start gap-2">
                                    <Checkbox
                                        v-model="form.use_custom_date"
                                        binary
                                        inputId="issuance-custom-date"
                                        class="mt-0.5"
                                    />
                                    <div>
                                        <label
                                            for="issuance-custom-date"
                                            class="cursor-pointer text-sm font-medium text-[#00164d]"
                                        >
                                            Use custom issuance date
                                        </label>
                                        <p class="mt-0.5 text-xs text-[#4a6490]">
                                            Turn this on when encoding past
                                            hard-copy records. Otherwise the
                                            system uses today's date.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-if="form.use_custom_date"
                                    class="max-w-xs"
                                >
                                    <label
                                        class="mb-1 block text-sm font-medium text-[#00164d]"
                                        >Issuance Date
                                        <span class="text-[#ce1126]"
                                            >*</span
                                        ></label
                                    >
                                    <InputText
                                        v-model="form.issued_date"
                                        type="date"
                                        class="w-full"
                                        :max="todayDate"
                                        required
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="issuance-section">
                        <div
                            class="mb-3 flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p class="issuance-section-label">
                                    {{
                                        form.issuance_type === "items"
                                            ? "Item lines"
                                            : "Equipment lines"
                                    }}
                                </p>
                                <p class="mt-0.5 text-sm text-[#4a6490]">
                                    <template
                                        v-if="form.issuance_type === 'items'"
                                    >
                                        Add one or more items and quantities.
                                        Leave Inventory No. blank to let the
                                        system generate one.
                                    </template>
                                    <template v-else>
                                        List each equipment from the paper form.
                                        For New (fresh) equipment, you must
                                        change the Property No. to the number on
                                        the paper. The original listing in
                                        Supply Master will not change. Inventory
                                        No. is optional.
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                            <UiButton
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addLine"
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
                                        d="M12 4.5v15m7.5-7.5h-15"
                                    />
                                </svg>
                                Add Line
                            </UiButton>
                            <UiButton
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="!canCancelLines"
                                @click="resetLines"
                            >
                                Cancel
                            </UiButton>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(line, index) in form.items"
                                :key="index"
                                class="space-y-3 border border-[#a8b8d4] bg-transparent p-3"
                            >
                            <div
                                class="issuance-line-row grid gap-3"
                                :class="
                                    form.issuance_type === 'equipments'
                                        ? 'md:grid-cols-[minmax(0,1.15fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_minmax(0,9.75rem)_7.25rem_auto]'
                                        : 'md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_8.75rem_auto]'
                                "
                            >
                                <div class="issuance-line-field min-w-0">
                                    <label class="issuance-line-label">
                                        {{
                                            form.issuance_type === "items"
                                                ? "Item"
                                                : "Equipment"
                                        }}
                                        <span class="text-[#ce1126]">*</span>
                                    </label>
                                    <Select
                                        v-if="form.issuance_type === 'items'"
                                        v-model="line.item_id"
                                        :options="items"
                                        optionLabel="label"
                                        optionValue="id"
                                        placeholder="Select item"
                                        class="issuance-line-control min-w-0 w-full"
                                        filter
                                        @update:model-value="
                                            onItemSelected(line, $event)
                                        "
                                    />
                                    <Select
                                        v-else
                                        v-model="line.equipment_id"
                                        :options="equipments"
                                        optionLabel="label"
                                        optionValue="id"
                                        placeholder="Select equipment"
                                        class="issuance-line-control min-w-0 w-full"
                                        filter
                                        @update:model-value="
                                            onEquipmentSelected(line, $event)
                                        "
                                    />
                                    <button
                                        v-if="form.issuance_type === 'equipments'"
                                        type="button"
                                        class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-[#4a6490] hover:text-[#00164d]"
                                        @click="openEquipmentInfo(line)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                                            />
                                        </svg>
                                        Info
                                    </button>
                                </div>
                                <div
                                    v-if="form.issuance_type === 'equipments'"
                                    class="issuance-line-field min-w-0"
                                >
                                    <label class="issuance-line-label">
                                        Property No.
                                        <span class="text-[#ce1126]">*</span>
                                    </label>
                                    <InputText
                                        v-model="line.property_number"
                                        class="issuance-line-control w-full"
                                        placeholder="Type the Property No. from the paper"
                                        :readonly="isPropertyLocked(line)"
                                        required
                                    />
                                    <p
                                        class="issuance-line-hint"
                                        :class="{
                                            'issuance-line-hint-warn':
                                                usesDefaultFreshProperty(line),
                                        }"
                                    >
                                        {{ propertyNumberHint(line) }}
                                    </p>
                                </div>
                                <div class="issuance-line-field min-w-0">
                                    <label class="issuance-line-label">
                                        Inventory No.
                                    </label>
                                    <InputText
                                        v-model="line.inventory_number"
                                        class="issuance-line-control w-full"
                                        :placeholder="
                                            form.issuance_type === 'items'
                                                ? 'Leave blank to auto-generate'
                                                : 'Optional'
                                        "
                                    />
                                    <p
                                        v-if="form.issuance_type === 'equipments'"
                                        class="issuance-line-hint"
                                    >
                                        Optional. Leave blank if the paper has
                                        none.
                                    </p>
                                </div>
                                <div
                                    v-if="form.issuance_type === 'equipments'"
                                    class="issuance-line-field min-w-0"
                                >
                                    <label class="issuance-line-label">
                                        Date Acquired
                                    </label>
                                    <InputText
                                        v-model="line.date_acquired"
                                        type="date"
                                        class="issuance-line-control w-full"
                                        :max="todayDate"
                                    />
                                </div>
                                <div class="issuance-line-field min-w-0">
                                    <label class="issuance-line-label">
                                        Qty
                                        <span class="text-[#ce1126]">*</span>
                                    </label>
                                    <InputNumber
                                        v-model="line.quantity"
                                        :min="1"
                                        placeholder="Qty"
                                        class="issuance-line-control min-w-0 w-full"
                                        inputClass="w-full"
                                    />
                                </div>
                                <div class="issuance-line-field issuance-line-action">
                                    <span class="issuance-line-label" aria-hidden="true">&nbsp;</span>
                                    <UiButton
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="shrink-0 text-[#ce1126] hover:bg-[#fff1f2] hover:text-[#ce1126]"
                                        :disabled="form.items.length === 1"
                                        @click="removeLine(index)"
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
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916A2.25 2.25 0 0013.5 2.25h-3A2.25 2.25 0 008.25 4.5v.916m7.5 0a48.667 48.667 0 00-7.5 0"
                                            />
                                        </svg>
                                    </UiButton>
                                </div>
                            </div>

                            <div
                                v-if="
                                    form.issuance_type === 'equipments' &&
                                    line.equipment_id
                                "
                                class="issuance-line-specs"
                            >
                                <p class="issuance-line-specs-label">Specs</p>
                                <p class="issuance-line-specs-value">
                                    {{
                                        equipmentSpecs(line.equipment_id) ||
                                        "No specs recorded for this equipment."
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="issuance-section">
                        <label
                            class="mb-1 block text-sm font-medium text-[#00164d]"
                            >Remarks</label
                        >
                        <Textarea
                            v-model="form.remarks"
                            class="w-full"
                            rows="2"
                            placeholder="Optional notes from the hard-copy form"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <UiButton type="submit" :loading="loading">
                        Process Issuance
                    </UiButton>
                    <UiButton
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="resetForm"
                    >
                        Clear Form
                    </UiButton>
                </div>
            </form>
        </div>

        <UiCard>
            <template #header>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="shadcn-card-title">Issuance History</h3>
                        <p class="shadcn-card-description">
                            Recent issuances recorded from hard-copy forms.
                        </p>
                    </div>
                    <div class="history-type-tabs w-full sm:w-auto">
                        <button
                            v-for="tab in historyTypeTabs"
                            :key="tab.key"
                            type="button"
                            class="history-type-tab"
                            :class="{
                                'history-type-tab-active':
                                    historyType === tab.key,
                            }"
                            @click="historyType = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </template>

            <TableFilters
                v-model="filters"
                :filters="filterConfig"
                :has-active-filters="hasActiveFilters"
                :result-count="visibleIssuances.length"
                @reset="resetFilters"
            />

            <div class="obims-table-wrap">
                <DataTable
                    :value="visibleIssuances"
                    :loading="loadingList"
                    paginator
                    :rows="10"
                    data-key="id"
                    class="rounded-md border border-[#a8b8d4] table-row-clickable"
                    @row-click="onIssuanceRowClick"
                >
                    <Column field="issuance_number" header="Issuance No." />
                    <Column
                        v-if="historyType === 'equipments'"
                        header="Property No."
                    >
                        <template #body="{ data }">
                            <span class="text-sm text-[#00164d]">{{
                                formatIssuedPropertyNumbers(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        v-if="historyType === 'equipments'"
                        header="Inventory No."
                    >
                        <template #body="{ data }">
                            <span class="text-sm text-[#00164d]">{{
                                formatIssuedInventoryNumbers(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Type">
                        <template #body="{ data }">{{
                            issuanceTypeLabel(data)
                        }}</template>
                    </Column>
                    <Column header="Department">
                        <template #body="{ data }">{{
                            departmentName(data)
                        }}</template>
                    </Column>
                    <Column header="Issued By">
                        <template #body="{ data }">{{
                            data.issuer?.name
                        }}</template>
                    </Column>
                    <Column header="Received By">
                        <template #body="{ data }">{{
                            receiverName(data)
                        }}</template>
                    </Column>
                    <Column header="Issued">
                        <template #body="{ data }">
                            <span class="text-sm text-[#00164d]">{{
                                formatIssuedLines(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column v-if="historyType === 'equipments'" header="Specs">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-sm text-[#00164d]">{{
                                formatIssuedSpecs(data)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Total Qty">
                        <template #body="{ data }">{{
                            totalQuantity(data)
                        }}</template>
                    </Column>
                    <Column header="Issuance Date">
                        <template #body="{ data }">{{
                            formatDate(data.issued_date)
                        }}</template>
                    </Column>
                    <Column
                        v-if="historyType === 'equipments'"
                        header="Date Acquired"
                    >
                        <template #body="{ data }">
                            {{ issuanceDateAcquired(data) }}
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 5rem">
                        <template #body="{ data }">
                            <UiButton
                                variant="ghost"
                                size="icon"
                                title="View issuance details"
                                class="table-row-actions"
                                @click.stop="viewIssuance(data)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                            </UiButton>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </UiCard>

        <Dialog
            v-model:visible="viewDialogVisible"
            modal
            header="Issuance Details"
            :style="{ width: '720px' }"
        >
            <div
                v-if="selectedIssuance"
                class="space-y-4 pt-2 text-sm text-[#00164d]"
            >
                <div class="border border-[#a8b8d4] bg-[#f4f7fb] p-4">
                    <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                        Issuance No.
                    </p>
                    <p class="mt-1 text-base font-semibold">
                        {{ selectedIssuance.issuance_number || "—" }}
                    </p>
                    <p class="mt-0.5 text-xs text-[#4a6490]">
                        {{ issuanceTypeLabel(selectedIssuance) }}
                        · {{ formatDate(selectedIssuance.issued_date) }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Department
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ departmentName(selectedIssuance) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Received By
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ receiverName(selectedIssuance) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Issued By
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ selectedIssuance.issuer?.name || "—" }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-[#4a6490]">
                            Total Qty
                        </p>
                        <p class="mt-0.5 font-medium">
                            {{ totalQuantity(selectedIssuance) }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs uppercase tracking-wide text-[#4a6490]">
                        Issued Lines
                    </p>
                    <div class="obims-table-wrap">
                        <DataTable
                            :value="selectedIssuance.details ?? []"
                            class="rounded-md border border-[#a8b8d4]"
                        >
                            <Column header="Name">
                                <template #body="{ data }">
                                    {{
                                        data.equipment?.name ||
                                        data.item?.item_name ||
                                        "—"
                                    }}
                                </template>
                            </Column>
                            <Column
                                v-if="
                                    issuanceTypeKey(selectedIssuance) !==
                                    'items'
                                "
                                header="Property No."
                            >
                                <template #body="{ data }">
                                    {{
                                        data.property_number ||
                                        data.equipment?.property_number ||
                                        "—"
                                    }}
                                </template>
                            </Column>
                            <Column
                                header="Inventory No."
                            >
                                <template #body="{ data }">
                                    {{
                                        data.inventory_number ||
                                        data.equipment?.inventory_number ||
                                        data.item?.inventory_number ||
                                        "—"
                                    }}
                                </template>
                            </Column>
                            <Column
                                v-if="
                                    issuanceTypeKey(selectedIssuance) !==
                                    'equipments'
                                "
                                header="Barcode / Item No."
                            >
                                <template #body="{ data }">
                                    {{
                                        data.item?.barcode ||
                                        data.item?.item_number ||
                                        data.barcode ||
                                        "—"
                                    }}
                                </template>
                            </Column>
                            <Column
                                v-if="
                                    issuanceTypeKey(selectedIssuance) !==
                                    'items'
                                "
                                header="Date Acquired"
                            >
                                <template #body="{ data }">
                                    {{
                                        formatDateOnly(
                                            data.date_acquired ||
                                                data.equipment?.date_acquired,
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column
                                v-if="
                                    issuanceTypeKey(selectedIssuance) !==
                                    'items'
                                "
                                header="Specs"
                            >
                                <template #body="{ data }">
                                    <span class="line-clamp-3">{{
                                        data.equipment?.specs || "—"
                                    }}</span>
                                </template>
                            </Column>
                            <Column field="quantity" header="Qty" />
                        </DataTable>
                    </div>
                </div>
            </div>
            <template #footer>
                <UiButton variant="outline" @click="viewDialogVisible = false"
                    >Close</UiButton
                >
            </template>
        </Dialog>

        <Dialog
            v-model:visible="equipmentInfoVisible"
            modal
            header="Equipment Info"
            :style="{ width: '560px' }"
        >
            <div class="space-y-4 pt-2 text-sm text-[#00164d]">
                <div
                    v-if="equipmentInfoRecord"
                    class="border border-[#a8b8d4] bg-[#f4f7fb] p-4"
                >
                    <p class="text-base font-semibold">
                        {{ equipmentInfoRecord.name || "—" }}
                    </p>
                    <p class="mt-1 text-xs text-[#4a6490]">
                        Status:
                        {{ equipmentStatusWord(equipmentInfoRecord) }}
                    </p>
                    <p
                        v-if="!isReturnedEquipment(equipmentInfoRecord)"
                        class="mt-3 border border-[#ce1126] bg-[#fff5f5] p-3 text-sm text-[#00164d]"
                    >
                        <span class="font-semibold">New equipment.</span>
                        You must change the Property No. to the number on the
                        paper form. The default listing cannot be used.
                        <span
                            v-if="
                                usesDefaultFreshProperty(equipmentInfoLine)
                            "
                            class="mt-1 block font-medium text-[#ce1126]"
                        >
                            The Property No. is still the default. Change it
                            before issuing.
                        </span>
                    </p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                            >
                                Property No.
                            </p>
                            <p class="mt-0.5 font-medium">
                                {{
                                    equipmentInfoLine?.property_number ||
                                    equipmentInfoRecord.property_number ||
                                    "—"
                                }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                            >
                                Inventory No.
                            </p>
                            <p class="mt-0.5 font-medium">
                                {{
                                    equipmentInfoLine?.inventory_number ||
                                    equipmentInfoRecord.inventory_number ||
                                    "—"
                                }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                            >
                                Date Acquired
                            </p>
                            <p class="mt-0.5 font-medium">
                                {{
                                    equipmentInfoLine?.date_acquired ||
                                    equipmentInfoRecord.date_acquired ||
                                    "—"
                                }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                            >
                                Remaining life span
                            </p>
                            <p class="mt-0.5 font-medium">
                                {{
                                    formatEquipmentLifeSpan(
                                        equipmentInfoRecord,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="equipmentInfoRecord.specs"
                        class="mt-3 border-t border-[#d7e0ef] pt-3"
                    >
                        <p
                            class="text-[10px] font-semibold uppercase tracking-wide text-[#4a6490]"
                        >
                            Specs
                        </p>
                        <p class="mt-1 whitespace-pre-wrap">
                            {{ equipmentInfoRecord.specs }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-[#4a6490]">
                    Select an equipment first to see its full details. You can
                    still read the numbering rules below.
                </p>

                <div
                    v-if="
                        !equipmentInfoRecord ||
                        !isReturnedEquipment(equipmentInfoRecord)
                    "
                    class="border border-[#a8b8d4] p-4"
                    :class="{
                        'border-[#ce1126] bg-[#fff5f5]':
                            equipmentInfoRecord &&
                            !isReturnedEquipment(equipmentInfoRecord),
                    }"
                >
                    <p class="font-semibold">New</p>
                    <p class="mt-1 text-[#4a6490]">
                        This is first-time stock from supply. You must change
                        the Property No. to the number on the paper form. The
                        default listing cannot be used. Inventory No. is
                        optional — leave it blank if the paper has none.
                    </p>
                    <p
                        v-if="
                            equipmentInfoRecord &&
                            !isReturnedEquipment(equipmentInfoRecord)
                        "
                        class="mt-2 font-medium text-[#ce1126]"
                    >
                        Selected: change the Property No. before you issue
                        this equipment.
                    </p>
                </div>

                <div
                    v-if="
                        !equipmentInfoRecord ||
                        isReturnedEquipment(equipmentInfoRecord)
                    "
                    class="border border-[#a8b8d4] p-4"
                >
                    <p class="font-semibold">Used</p>
                    <p class="mt-1 text-[#4a6490]">
                        This was returned before and is being issued again.
                        You decide whether the Property No. should change.
                        Keep it if the paper shows the same number. Change it
                        only if the paper shows a different number. Inventory
                        No. is optional.
                    </p>
                    <div
                        v-if="
                            equipmentInfoLine &&
                            isReturnedEquipment(equipmentInfoRecord)
                        "
                        class="mt-3 flex flex-col gap-2 sm:flex-row"
                    >
                        <UiButton
                            type="button"
                            variant="outline"
                            @click="setReturnedPropertyChoice(false)"
                        >
                            Keep Property No.
                        </UiButton>
                        <UiButton
                            type="button"
                            @click="setReturnedPropertyChoice(true)"
                        >
                            Change Property No.
                        </UiButton>
                    </div>
                </div>
            </div>
            <template #footer>
                <UiButton
                    variant="outline"
                    @click="equipmentInfoVisible = false"
                    >Close</UiButton
                >
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { computed, h, onMounted, reactive, ref, watch } from "vue";
import { useNotify } from "../../composables/useNotify";
import Select from "primevue/select";
import AutoComplete from "primevue/autocomplete";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";
import Checkbox from "primevue/checkbox";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import UiCard from "../../components/ui/UiCard.vue";
import UiButton from "../../components/ui/UiButton.vue";
import TableFilters from "../../components/TableFilters.vue";
import { useTableFilters } from "../../composables/useTableFilters";
import api from "../../services/api";
import { formatEquipmentLifeSpan, equipmentOriginLabel } from "../../utils/equipmentLifeSpan";

const IconItems = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4",
                }),
            ],
        );
    },
};

const IconEquipments = {
    render() {
        return h(
            "svg",
            {
                fill: "none",
                viewBox: "0 0 24 24",
                stroke: "currentColor",
                "stroke-width": "2",
            },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    d: "M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5",
                }),
            ],
        );
    },
};

const issuanceTypeTabs = [
    {
        key: "items",
        label: "Items",
        description: "Issue common supplies and consumables",
        icon: IconItems,
    },
    {
        key: "equipments",
        label: "Equipments",
        description: "Issue property-tagged equipment",
        icon: IconEquipments,
    },
];

const historyTypeTabs = [
    { key: "items", label: "Items" },
    { key: "equipments", label: "Equipments" },
];

const notify = useNotify();
const departments = ref([]);
const employees = ref([]);
const items = ref([]);
const equipments = ref([]);
const issuances = ref([]);
const loading = ref(false);
const loadingList = ref(false);
const historyType = ref("items");
const viewDialogVisible = ref(false);
const selectedIssuance = ref(null);
const equipmentInfoVisible = ref(false);
const equipmentInfoLine = ref(null);

const equipmentInfoRecord = computed(() => {
    const equipmentId = equipmentInfoLine.value?.equipment_id;
    if (!equipmentId) {
        return null;
    }

    return equipments.value.find((row) => row.id === equipmentId) ?? null;
});

const form = reactive({
    issuance_type: "items",
    issuance_number: "",
    department_id: null,
    remarks: "",
    use_custom_date: false,
    issued_date: "",
    items: [emptyLine()],
});

const receivedByInput = ref(null);
const receiverSuggestions = ref([]);

const todayDate = computed(() => new Date().toISOString().slice(0, 10));

function emptyLine() {
    return {
        item_id: null,
        equipment_id: null,
        property_number: "",
        inventory_number: "",
        date_acquired: "",
        quantity: 1,
        can_change_property: true,
        default_property_number: "",
    };
}

function onItemSelected(line, itemId) {
    const item = items.value.find((row) => row.id === itemId);
    line.inventory_number = item?.inventory_number || "";
}

function onEquipmentSelected(line, equipmentId) {
    const equipment = equipments.value.find((row) => row.id === equipmentId);
    line.default_property_number = equipment?.property_number || "";
    line.property_number = equipment?.property_number || "";
    line.inventory_number = equipment?.inventory_number || "";
    line.date_acquired = toDateInputValue(equipment?.date_acquired);
    line.can_change_property = !isReturnedEquipment(equipment);
}

function isReturnedEquipment(equipment) {
    if (!equipment) {
        return false;
    }

    return (
        equipment.origin === "returned" || Boolean(equipment.source_return_id)
    );
}

function usesDefaultFreshProperty(line) {
    const equipment = equipments.value.find(
        (row) => row.id === line.equipment_id,
    );
    if (!equipment || isReturnedEquipment(equipment)) {
        return false;
    }

    const current = (line.property_number || "").trim();
    const original = (
        line.default_property_number ||
        equipment.property_number ||
        ""
    ).trim();

    if (!current || !original) {
        return false;
    }

    return current.toLowerCase() === original.toLowerCase();
}

function equipmentStatusWord(equipment) {
    return isReturnedEquipment(equipment) ? "Used" : "New";
}

function isPropertyLocked(line) {
    const equipment = equipments.value.find(
        (row) => row.id === line.equipment_id,
    );

    return isReturnedEquipment(equipment) && !line.can_change_property;
}

function propertyNumberHint(line) {
    const equipment = equipments.value.find(
        (row) => row.id === line.equipment_id,
    );

    if (isReturnedEquipment(equipment)) {
        return line.can_change_property
            ? "You chose to change this. Type the Property No. from the paper form."
            : "Kept as listed. Open Info if you need to change it.";
    }

    if (usesDefaultFreshProperty(line)) {
        return "Change this Property No. The default listing cannot be used for New equipment.";
    }

    return "Required. Use the Property No. from the paper form.";
}

function openEquipmentInfo(line) {
    equipmentInfoLine.value = line;
    equipmentInfoVisible.value = true;
}

function setReturnedPropertyChoice(canChange) {
    if (equipmentInfoLine.value) {
        equipmentInfoLine.value.can_change_property = canChange;
    }
    equipmentInfoVisible.value = false;
}

function equipmentSpecs(equipmentId) {
    const equipment = equipments.value.find((row) => row.id === equipmentId);
    return String(equipment?.specs || "").trim();
}

const employeeOptions = computed(() => {
    if (!form.department_id) {
        return [];
    }

    return employees.value.filter(
        (employee) => employee.department_id === form.department_id,
    );
});

const filterConfig = computed(() => [
    {
        key: "search",
        type: "search",
        label: "Search",
        placeholder: "Issuance no., department, receiver...",
        fields: [
            "issuance_number",
            "department.name",
            "receiver.department.name",
            "receiver.name",
            "received_by_name",
            "issuer.name",
            "details.property_number",
            "details.inventory_number",
            "details.equipment.property_number",
            "details.equipment.inventory_number",
        ],
    },
    {
        key: "dateFrom",
        type: "date",
        label: "Date From",
        field: "issued_date",
        mode: "from",
    },
    {
        key: "dateTo",
        type: "date",
        label: "Date To",
        field: "issued_date",
        mode: "to",
    },
]);

const {
    filters,
    filteredItems: filteredIssuances,
    hasActiveFilters,
    resetFilters,
} = useTableFilters(issuances, filterConfig);

const visibleIssuances = computed(() =>
    filteredIssuances.value.filter((issuance) => {
        const type = issuanceTypeKey(issuance);
        if (historyType.value === "items") {
            return type === "items" || type === "mixed";
        }
        return type === "equipments" || type === "mixed";
    }),
);

watch(
    () => form.department_id,
    () => {
        receivedByInput.value = null;
        receiverSuggestions.value = [];
    },
);

watch(
    () => form.issuance_type,
    (type) => {
        historyType.value = type;
    },
);

function searchReceivers(event) {
    const query = (event.query || "").trim().toLowerCase();
    const options = employeeOptions.value;

    receiverSuggestions.value = query
        ? options.filter((employee) =>
              employee.name.toLowerCase().includes(query),
          )
        : options;
}

function setIssuanceType(type) {
    if (form.issuance_type === type) {
        return;
    }

    form.issuance_type = type;
    form.items = [emptyLine()];
}

function addLine() {
    form.items.push(emptyLine());
}

function lineHasData(line) {
    if (form.issuance_type === "items") {
        return Boolean(
            line.item_id ||
                (line.inventory_number || "").trim() ||
                line.quantity !== 1,
        );
    }

    return Boolean(
        line.equipment_id ||
            (line.property_number || "").trim() ||
            (line.inventory_number || "").trim() ||
            (line.date_acquired || "").trim() ||
            line.quantity !== 1 ||
            line.can_change_property === false,
    );
}

const canCancelLines = computed(
    () => form.items.length > 1 || form.items.some((line) => lineHasData(line)),
);

function resetLines() {
    if (!canCancelLines.value) {
        return;
    }

    form.items = [emptyLine()];
}

function removeLine(index) {
    if (form.items.length === 1) {
        return;
    }

    form.items.splice(index, 1);
}

function resetForm() {
    form.issuance_number = "";
    form.department_id = null;
    receivedByInput.value = null;
    receiverSuggestions.value = [];
    form.remarks = "";
    form.use_custom_date = false;
    form.issued_date = "";
    form.items = [emptyLine()];
}

function departmentName(issuance) {
    return (
        issuance.department?.name ||
        issuance.receiver?.department?.name ||
        "—"
    );
}

function receiverName(issuance) {
    return issuance.receiver?.name || issuance.received_by_name || "—";
}

function resolveReceiverPayload() {
    const value = receivedByInput.value;

    if (value && typeof value === "object" && value.id) {
        return {
            received_by: value.id,
            received_by_name: null,
        };
    }

    const typedName = typeof value === "string" ? value.trim() : "";

    return {
        received_by: null,
        received_by_name: typedName || null,
    };
}

function issuanceTypeKey(issuance) {
    const hasEquipment = (issuance.details ?? []).some(
        (detail) => detail.equipment_id || detail.equipment,
    );
    const hasItem = (issuance.details ?? []).some(
        (detail) => detail.item_id || detail.item,
    );

    if (hasEquipment && !hasItem) return "equipments";
    if (hasItem && !hasEquipment) return "items";
    if (hasEquipment && hasItem) return "mixed";
    return "unknown";
}

function issuanceTypeLabel(issuance) {
    const key = issuanceTypeKey(issuance);
    if (key === "equipments") return "Equipments";
    if (key === "items") return "Items";
    if (key === "mixed") return "Mixed";
    return "—";
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : "";
}

function toDateInputValue(value) {
    if (!value) {
        return "";
    }

    return String(value).slice(0, 10);
}

function formatDateOnly(value) {
    if (!value) {
        return "—";
    }

    const raw = String(value).slice(0, 10);
    const [year, month, day] = raw.split("-");
    if (!year || !month || !day) {
        return raw;
    }

    return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString();
}

function issuanceDateAcquired(issuance) {
    const dates = (issuance.details ?? [])
        .map((detail) => detail.date_acquired || detail.equipment?.date_acquired)
        .map((value) => formatDateOnly(value))
        .filter((value) => value && value !== "—");

    if (!dates.length) {
        return "—";
    }

    return [...new Set(dates)].join(", ");
}

function formatIssuedLines(issuance) {
    return (issuance.details ?? [])
        .map((detail) => {
            const name =
                detail.equipment?.name ?? detail.item?.item_name ?? "Line item";
            return `${name} (${detail.quantity})`;
        })
        .join(", ");
}

function formatIssuedSpecs(issuance) {
    const specs = (issuance.details ?? [])
        .map((detail) => detail.equipment?.specs)
        .filter((value) => String(value || "").trim() !== "");

    if (!specs.length) {
        return "—";
    }

    return [...new Set(specs)].join("; ");
}

function uniqueDetailValues(issuance, pick) {
    const values = (issuance.details ?? [])
        .map(pick)
        .map((value) => String(value || "").trim())
        .filter(Boolean);

    if (!values.length) {
        return "—";
    }

    return [...new Set(values)].join(", ");
}

function formatIssuedPropertyNumbers(issuance) {
    return uniqueDetailValues(
        issuance,
        (detail) => detail.property_number || detail.equipment?.property_number,
    );
}

function formatIssuedInventoryNumbers(issuance) {
    return uniqueDetailValues(
        issuance,
        (detail) =>
            detail.inventory_number || detail.equipment?.inventory_number,
    );
}

function isTableActionClick(event) {
    const target = event?.originalEvent?.target;
    return Boolean(
        target?.closest?.("button, a, input, textarea, select, .table-row-actions"),
    );
}

function onIssuanceRowClick(event) {
    if (isTableActionClick(event) || !event?.data) {
        return;
    }

    viewIssuance(event.data);
}

function viewIssuance(issuance) {
    selectedIssuance.value = issuance;
    viewDialogVisible.value = true;
}

function totalQuantity(issuance) {
    return (issuance.details ?? []).reduce(
        (sum, detail) => sum + detail.quantity,
        0,
    );
}

function buildPayload() {
    const payload = {
        issuance_type: form.issuance_type,
        department_id: form.department_id,
        ...resolveReceiverPayload(),
        remarks: form.remarks || null,
        use_custom_date: form.use_custom_date,
        items: form.items.map((line) => {
            if (form.issuance_type === "items") {
                return {
                    item_id: line.item_id,
                    inventory_number: (line.inventory_number || "").trim() || null,
                    quantity: line.quantity,
                };
            }

            return {
                equipment_id: line.equipment_id,
                property_number: (line.property_number || "").trim(),
                inventory_number: (line.inventory_number || "").trim() || null,
                date_acquired: (line.date_acquired || "").trim() || null,
                quantity: line.quantity,
            };
        }),
    };

    const referenceNumber = (form.issuance_number || "").trim();
    if (referenceNumber) {
        payload.issuance_number = referenceNumber;
    }

    if (form.use_custom_date && form.issued_date) {
        payload.issued_date = form.issued_date;
    }

    return payload;
}

async function submit() {
    const receiver = resolveReceiverPayload();

    if (!form.department_id || (!receiver.received_by && !receiver.received_by_name)) {
        notify.warn("Please select a department and enter who received the items.");
        return;
    }

    if (form.use_custom_date && !form.issued_date) {
        notify.warn("Please select the custom issuance date.");
        return;
    }

    const incomplete = form.items.some((line) => {
        if (!line.quantity || line.quantity < 1) return true;
        if (form.issuance_type === "items") {
            return !line.item_id;
        }

        return !line.equipment_id || !(line.property_number || "").trim();
    });

    if (incomplete) {
        notify.warn(
            form.issuance_type === "equipments"
                ? "Please select equipment, enter a property number, and quantity for every line."
                : "Please complete all issuance lines before submitting.",
        );
        return;
    }

    if (
        form.issuance_type === "equipments" &&
        form.items.some((line) => usesDefaultFreshProperty(line))
    ) {
        notify.warn(
            "Change the Property No. for New (fresh) equipment. The default listing cannot be used.",
        );
        return;
    }

    loading.value = true;
    try {
        await api.post("/issuances", buildPayload());
        notify.success(
            "Issuance was recorded successfully.",
            "Issuance completed",
        );
        resetForm();
        await loadLookups();
        await loadIssuances();
    } catch (error) {
        notify.error(
            error.response?.data?.message ||
                error.message ||
                "Unable to process issuance.",
            "Issuance failed",
        );
    } finally {
        loading.value = false;
    }
}

async function loadLookups() {
    try {
        const [deptRes, empRes, itemRes, equipmentRes] = await Promise.all([
            api.get("/departments/list"),
            api.get("/employees/list"),
            api.get("/items/list", { params: { all: 1 } }),
            api.get("/equipments/list"),
        ]);

        departments.value = deptRes.data.data ?? deptRes.data;
        employees.value = empRes.data.data ?? empRes.data;
        items.value = (Array.isArray(itemRes.data) ? itemRes.data : itemRes.data?.data ?? []).map((item) => ({
            id: item.id,
            inventory_number: item.inventory_number || "",
            label: `${item.item_name}${item.barcode || item.item_number ? ` (${item.barcode || item.item_number})` : ""} · Stock: ${item.current_stock ?? 0}`,
        }));
        equipments.value = (equipmentRes.data ?? []).map((equipment) => {
            const lifeSpan = formatEquipmentLifeSpan(equipment);

            return {
                id: equipment.id,
                name: equipment.name,
                property_number: equipment.property_number || "",
                inventory_number: equipment.inventory_number || "",
                date_acquired: toDateInputValue(equipment.date_acquired),
                specs: equipment.specs || "",
                origin: equipment.origin,
                source_return_id: equipment.source_return_id,
                life_span_years: equipment.life_span_years,
                lifespan_expires_on: equipment.lifespan_expires_on,
                label: [
                    equipment.name,
                    equipment.property_number || null,
                    Number(equipment.quantity) > 0 ? "Available" : "Not Available",
                    equipmentOriginLabel(equipment),
                    lifeSpan !== "—" ? lifeSpan : null,
                ]
                    .filter(Boolean)
                    .join(" · "),
            };
        });
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load issuance form data.",
        );
    }
}

async function loadIssuances() {
    loadingList.value = true;
    try {
        const { data } = await api.get("/issuances/list");
        issuances.value = data.data ?? data;
    } catch (error) {
        notify.error(
            error.response?.data?.message || "Unable to load issuance history.",
        );
    } finally {
        loadingList.value = false;
    }
}

onMounted(async () => {
    await loadLookups();
    await loadIssuances();
});
</script>

<style scoped>
.issuance-section + .issuance-section {
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid #d7e0ef;
}

.issuance-section-label {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #4a6490;
}

.issuance-section > .issuance-section-label {
    margin-bottom: 0.75rem;
}

.issuance-line-row {
    align-items: start;
}

.issuance-line-field {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.issuance-line-label {
    display: block;
    margin-bottom: 0.25rem;
    height: 1.25rem;
    overflow: hidden;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25rem;
    color: #00164d;
    white-space: nowrap;
}

.issuance-line-action {
    width: 2.25rem;
    justify-self: end;
}

.issuance-line-control,
.issuance-line-control :deep(.p-select),
.issuance-line-control :deep(.p-inputtext),
.issuance-line-control :deep(.p-inputnumber),
.issuance-line-control :deep(.p-inputnumber-input) {
    width: 100%;
    height: 2.25rem;
}

.issuance-line-action :deep(.shadcn-btn-icon) {
    height: 2.25rem;
    width: 2.25rem;
}

.issuance-line-hint {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    line-height: 1.3;
    color: #4a6490;
}

.issuance-line-hint-warn {
    font-weight: 600;
    color: #ce1126;
}

.issuance-line-specs {
    border-top: 1px solid #d7e0ef;
    padding-top: 0.75rem;
}

.issuance-line-specs-label {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #4a6490;
}

.issuance-line-specs-value {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    line-height: 1.4;
    color: #00164d;
    white-space: pre-wrap;
}

.history-type-tabs {
    display: flex;
    width: 100%;
    gap: 0;
    border: 1px solid #a8b8d4;
    background: transparent;
    padding: 0;
}

@media (min-width: 640px) {
    .history-type-tabs {
        display: inline-flex;
        width: auto;
    }
}

.history-type-tab {
    flex: 1;
    border-radius: 0;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4a6490;
    transition: all 0.15s ease;
    text-align: center;
    border-right: 1px solid #a8b8d4;
}

.history-type-tab:last-child {
    border-right: 0;
}

@media (min-width: 640px) {
    .history-type-tab {
        flex: none;
        padding: 0.5rem 1rem;
    }
}

.history-type-tab:hover {
    color: #001f6b;
}

.history-type-tab-active {
    background: #001f6b;
    color: white;
}
</style>
