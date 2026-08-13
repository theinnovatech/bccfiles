<template>
    <CrudPage
        title="Department"
        endpoint="/departments"
        :columns="[
            { field: 'name', header: 'Name' },
            { field: 'code', header: 'Code' },
        ]"
        :fields="[
            { key: 'name', label: 'Name', required: true },
            { key: 'code', label: 'Code', required: true },
            { key: 'is_active', label: 'Active', type: 'checkbox' },
        ]"
    />
</template>

<script setup>
import CrudPage from "../../components/CrudPage.vue";
</script>
