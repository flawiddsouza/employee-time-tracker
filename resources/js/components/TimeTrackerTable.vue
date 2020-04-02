<template>
    <div>
        <h5 class="d-flex justify-content-between align-items-end">
            <form @submit.prevent="fetchTimes(true)">
                <select class="form-control d-inline-block w-auto f-i" v-model="selectedUser" v-if="isAdmin" required style="width: 166px !important">
                    <option :value="{ id: 'All', name: 'All' }">All</option>
                    <option v-for="user in users" :value="user">{{ user.name }}</option>
                </select>

                <select class="form-control d-inline-block w-auto f-i" v-model="selectedRangeFilter">
                    <option v-if="isAdmin && !isWeekly">Today</option>
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
                    <template v-if="!isWeekly">
                        <th style="width: 9em" class="text-center" data-a-h="center" data-f-bold="true">Date</th>
                        <th style="width: 9em" class="text-center" data-a-h="center" data-f-bold="true" v-if="isAdmin && selectedUser.name === 'All'">Employee Name</th>
                    </template>
                    <template v-else>
                        <th style="width: 20em" class="text-center" data-a-h="center" data-f-bold="true" v-if="isAdmin && selectedUser.name === 'All'">Employee Name</th>
                        <th style="width: 9em" class="text-center" data-a-h="center" data-f-bold="true">Date</th>
                    </template>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">Start Time</th>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">End Time</th>
                    <th style="width: 7em" class="text-center" data-a-h="center" data-f-bold="true">Duration</th>
                    <th data-f-bold="true" v-if="!isWeekly">Work Report</th>
                    <th class="text-center" data-a-h="center" data-f-bold="true" v-else>Weekly Total</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="Object.keys(times).length > 0">
                    <template v-for="(timesGroupLevel1, date) in times">
                        <template v-for="(timesGroupLevel2, index1) in Object.values(timesGroupLevel1)">
                            <tr v-for="(time, index) in Object.values(timesGroupLevel2)">
                                <template v-if="!isWeekly">
                                    <td class="text-center align-middle" data-a-h="center" data-a-v="middle" data-t="d" data-num-fmt="dd/mmmm/yyyy" v-if="index1 === 0 && index === 0" :rowspan="rowspanForFirstColumn(timesGroupLevel1)">{{ formatDate(time.date) }}</td>
                                    <td class="text-center align-middle" data-a-h="center" data-a-v="middle" :rowspan="rowspanForSecondColumn(timesGroupLevel2)" v-if="isAdmin && selectedUser.name === 'All' && index === 0">{{ time.employee_name }}</td>
                                </template>
                                <template v-else>
                                    <td class="text-center align-middle" data-a-h="center" data-a-v="middle" v-if="isAdmin && selectedUser.name === 'All' && index1 === 0 && index === 0" :rowspan="rowspanForFirstColumn(timesGroupLevel1)">{{ time.employee_name }}</td>
                                    <td class="text-center align-middle" data-a-h="center" data-a-v="middle" data-t="d" data-num-fmt="dd/mmmm/yyyy" :rowspan="rowspanForSecondColumn(timesGroupLevel2)" v-if="index === 0">{{ formatDate(time.date) }}</td>
                                </template>
                                <td class="text-center" data-a-h="center">{{ formatTime(time.start_time) }}</td>
                                <td class="text-center" data-a-h="center">{{ formatTime(time.stop_time) }}</td>
                                <td class="text-center" data-a-h="center">{{ time.duration }}</td>
                                <td class="ws-pw" data-a-wrap="true" v-if="!isWeekly">{{ time.work_report }}</td>
                                <template v-else>
                                    <td class="text-center align-middle" data-a-h="center" data-a-v="middle" :rowspan="rowspanForFirstColumn(timesGroupLevel1)" v-if="index1 === 0 && index === 0">{{ calculateTotalDurationOverall(timesGroupLevel1) }}</td>
                                </template>
                            </tr>
                            <tr v-if="timesGroupLevel2.length > 1">
                                <td colspan="2" class="text-right" data-a-h="right">Total Duration</td>
                                <td colspan="1" class="text-center" data-a-h="center">{{ calculateTotalDuration(timesGroupLevel2) }}</td>
                                <td colspan="1" v-if="!isWeekly">{{ timesGroupLevel2.length }} entr<template v-if="timesGroupLevel2.length > 1">ies</template><template v-else>y</template></td>
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

const today = formatISO(new Date(), { representation: 'date' })
const currentWeekStartDate = startOfWeek(new Date(), { weekStartsOn: 1 })
const currentWeekEndDate = endOfWeek(currentWeekStartDate, { weekStartsOn: 1 })

export default {
    props: {
        isAdmin: Boolean,
        isWeekly: Boolean,
        fetchTimesOnCreated: Boolean,
    },
    mixins: [
        dateUtilsMixin
    ],
    data() {
        return {
            users: [],
            selectedUser: null,
            selectedRangeFilter: this.isAdmin && !this.isWeekly ? 'Today' : 'This Week',
            selectedFromDate: this.isAdmin && !this.isWeekly ? today : formatISO(currentWeekStartDate, { representation: 'date' }),
            selectedToDate: this.isAdmin && !this.isWeekly ? today : formatISO(currentWeekEndDate, { representation: 'date' }),
            times: []
        }
    },
    watch: {
        selectedRangeFilter() {

            if(this.selectedRangeFilter === 'Today') {
                this.selectedFromDate = today
                this.selectedToDate = today
            }

            if(this.selectedRangeFilter === 'This Week') {
                this.selectedFromDate = formatISO(currentWeekStartDate, { representation: 'date' })
                this.selectedToDate = formatISO(currentWeekEndDate, { representation: 'date' })
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

            axios.get(`/time-tracker/times?${this.isAdmin ? `user_id=${this.selectedUser.id}&` : ''}from=${this.selectedFromDate}&to=${this.selectedToDate}${this.isWeekly ? '&is_weekly=1' : ''}`).then(response => {
                this.times = response.data

                if(loader) {
                    loader.hide()
                }
            })
        },
        calculateTotalDuration(timesGroupLevel2, noConversion=false) {
            let totalDurationInSeconds = timesGroupLevel2.reduce((acc, currentItem) => acc + Number(currentItem.duration_in_seconds), 0)

            if(!noConversion) {
                return dateUtils.secondsInHHMMSS(totalDurationInSeconds)
            } else {
                return totalDurationInSeconds
            }
        },
        calculateTotalDurationOverall(timesGroupLevel1) {
            let totalDurationInSeconds = 0

            Object.values(timesGroupLevel1).forEach(timesGroupLevel2 => {
                totalDurationInSeconds += this.calculateTotalDuration(timesGroupLevel2, true)
            })

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
        },
        rowspanForSecondColumn(timesGroupLevel2) {
            return timesGroupLevel2.length + (timesGroupLevel2.length > 1 ? 1 : 0)
        },
        rowspanForFirstColumn(timesGroupLevel1) {
            let rowspan = 0

            Object.values(timesGroupLevel1).forEach(timesGroupLevel2 => {
                rowspan += this.rowspanForSecondColumn(timesGroupLevel2)
            })

            return rowspan
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
