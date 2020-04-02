<template>
    <div>
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link" :class="{ 'active': selectedTab === 1 }" href="#" @click.prevent="selectedTab = 1">Date Wise Timesheet</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" :class="{ 'active': selectedTab === 2 }" href="#" @click.prevent="selectedTab = 2">Weekly Total / Custom Date Range Timesheet</a>
            </li>
        </ul>

        <time-tracker-table class="mt-4" :is-admin="true" :fetch-times-on-created="true" v-if="selectedTab === 1" key="tab1"></time-tracker-table>
        <time-tracker-table class="mt-4" :is-admin="true" :is-weekly="true" :fetch-times-on-created="true" v-if="selectedTab === 2" key="tab2"></time-tracker-table>
    </div>
</template>

<script>
export default {
    data() {
        return {
            selectedTab: 1
        }
    }
}
</script>
