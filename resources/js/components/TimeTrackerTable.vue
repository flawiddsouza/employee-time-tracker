<template>
    <div>
        <h5 class="d-flex justify-content-between align-items-end">
            <form @submit.prevent="fetchTimes(true)">
                <select class="form-control d-inline-block w-auto f-i" v-model="selectedUser" v-if="isAdmin" required style="width: 166px !important">
                    <option :value="{ id: 'All', name: 'All' }">All</option>
                    <option v-for="user in users" :value="user">{{ user.name }}</option>
                </select>

                <select class="form-control d-inline-block w-auto f-i" v-model="selectedRangeFilter">
                    <option>This Week</option>
                    <option>Last Week</option>
                    <option>Custom Date Range</option>
                </select>

                <input type="date" class="form-control d-inline-block w-auto f-i" v-model="selectedFromDate" required @keydown.delete.prevent :disabled="selectedRangeFilter !== 'Custom Date Range'" :max="selectedToDate">
                <input type="date" class="form-control d-inline-block w-auto f-i" v-model="selectedToDate" required @keydown.delete.prevent :disabled="selectedRangeFilter !== 'Custom Date Range'" :min="selectedFromDate">

                <button class="btn btn-primary btn-lg f-i">Load</button>
            </form>

            <div>
                <button type="button" class="btn btn-primary btn-lg f-i" @click="exportTimes">Export Excel</button>
            </div>
        </h5>

        <table class="table table-bordered table-sm" ref="timesTable" :data-cols-width="(isAdmin ? '17,' : '') + '17,13,13,13,40'">
            <thead>
                <tr>
                    <th style="width: 9em" class="text-center" data-a-h="center" data-f-bold="true" v-if="isAdmin && selectedUser.name === 'All'">Employee Name</th>
                    <th style="width: 9em" class="text-center" data-a-h="center" data-f-bold="true">Date</th>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">Start Time</th>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">End Time</th>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">Duration</th>
                    <th data-f-bold="true">Work Report</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="Object.keys(times).length > 0">
                    <template v-for="(timesDateWise, date) in times">
                        <template v-for="timesUserWise in timesDateWise">
                            <tr v-for="(time, index) in Object.values(timesUserWise)">
                                <td class="text-center align-middle" data-a-h="center" data-a-v="middle" v-if="isAdmin && selectedUser.name === 'All' && index === 0" :rowspan="timesUserWise.length + (timesUserWise.length > 1 ? 1 : 0)">{{ time.employee_name }}</td>
                                <td class="text-center align-middle" data-a-h="center" data-a-v="middle" data-t="d" data-num-fmt="dd/mmmm/yyyy" :rowspan="timesUserWise.length + (timesUserWise.length > 1 ? 1 : 0)" v-if="index === 0">{{ formatDate(time.date) }}</td>
                                <td class="text-center" data-a-h="center">{{ formatTime(time.start_time) }}</td>
                                <td class="text-center" data-a-h="center">{{ formatTime(time.stop_time) }}</td>
                                <td class="text-center" data-a-h="center">{{ time.duration }}</td>
                                <td class="ws-pw" data-a-wrap="true">{{ time.work_report }}</td>
                            </tr>
                            <tr v-if="timesUserWise.length > 1">
                                <td colspan="2" class="text-right" data-a-h="right">Total Duration</td>
                                <td colspan="1" class="text-center" data-a-h="center">{{ calculateTotalDuration(timesUserWise) }}</td>
                                <td colspan="1">{{ timesUserWise.length }} entr<template v-if="timesUserWise.length > 1">ies</template><template v-else>y</template></td>
                            </tr>
                        </template>
                    </template>
                </template>
                <tr v-else>
                    <td :colspan="isAdmin && selectedUser.id === 'All' ? 6 : 5" class="text-center" data-a-h="center">No records found</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import formatISO from 'date-fns/formatISO'
import parseISO from 'date-fns/parseISO'
import { startOfWeek, endOfWeek, subWeeks } from 'date-fns'
import dateUtils from './../libs/dateUtils'
import dateUtilsMixin from './../mixins/dateUtilsMixin'
import TableToExcel from "@linways/table-to-excel"

export default {
    props: {
        isAdmin: Boolean,
        fetchTimesOnCreated: Boolean,
    },
    mixins: [
        dateUtilsMixin
    ],
    data() {
        return {
            users: [],
            selectedUser: null,
            selectedRangeFilter: 'This Week',
            selectedFromDate: formatISO(startOfWeek(new Date(), { weekStartsOn: 1 }), { representation: 'date' }),
            selectedToDate: formatISO(endOfWeek(new Date(), { weekStartsOn: 1 }), { representation: 'date' }),
            times: []
        }
    },
    watch: {
        selectedRangeFilter() {
            const currentWeekStartDate = startOfWeek(new Date(), { weekStartsOn: 1 })

            if(this.selectedRangeFilter === 'This Week') {
                this.selectedFromDate = formatISO(currentWeekStartDate, { representation: 'date' })
                this.selectedToDate = formatISO(endOfWeek(currentWeekStartDate, { weekStartsOn: 1 }), { representation: 'date' })
            }

            if(this.selectedRangeFilter === 'Last Week') {
                const lastWeekStartDate = startOfWeek(subWeeks(currentWeekStartDate, 1), { weekStartsOn: 1 })
                this.selectedFromDate = formatISO(lastWeekStartDate, { representation: 'date' })
                this.selectedToDate = formatISO(endOfWeek(lastWeekStartDate, { weekStartsOn: 1 }), { representation: 'date' })
            }
        },
        selectedFromDate() {
            this.times = []
        },
        selectedToDate() {
            this.times = []
        },
        selectedUser() {
            this.times = []
        }
    },
    methods: {
        fetchTimes(showLoader=false) {
            let loader = null

            if(showLoader) {
                loader = this.$loading.show()
            }

            axios.get(`/time-tracker/times?${this.selectedUser ? `user_id=${this.selectedUser.id}&` : ''}from=${this.selectedFromDate}&to=${this.selectedToDate}`).then(response => {
                this.times = response.data

                if(loader) {
                    loader.hide()
                }
            })
        },
        calculateTotalDuration(times) {
            let totalDurationInSeconds = times.reduce((acc, currentItem) => acc + Number(currentItem.duration_in_seconds), 0)

            return dateUtils.secondsInHHMMSS(totalDurationInSeconds)
        },
        exportTimes() {
            TableToExcel.convert(this.$refs.timesTable, {
                name: `Timesheet for ${this.isAdmin ? this.selectedUser.name : document.querySelector('#navbarDropdown').innerText.trim()} from ${this.formatDate(this.selectedFromDate)} to ${this.formatDate(this.selectedToDate)}.xlsx`
            })
        },
        fetchUsers() {
            axios.get(`/admin-panel/users`).then(response => {
                this.users = response.data
            })
        }
    },
    created() {
        if(this.isAdmin) {
            this.selectedUser = { id: 'All', name: 'All' }
            this.fetchUsers()
        }

        if(this.fetchTimesOnCreated) {
            this.fetchTimes()
        }
    }
}
</script>
