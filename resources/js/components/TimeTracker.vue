<template>
    <div>
        <h4>{{ formatDate(today) }} (Today) <template v-if="tracking">- {{ formatTime(startTime) }} - <span>{{ elapsedTime }}</span></template></h4>

        <button class="btn btn-primary" @click="startTracking" v-if="!tracking" :disabled="!activeTrackLoaded">Start Tracking</button>
        <button class="btn btn-primary" @click="stopTrackingBegin" v-else>Stop Tracking</button>

        <h5 class="mt-5 d-flex justify-content-between align-items-end">
            <div>
                <select class="form-control d-inline-block w-auto f-i" v-model="selectedRangeFilter">
                    <option>This Week</option>
                    <option>Last Week</option>
                    <option>Custom Date Range</option>
                </select>

                <input type="date" class="form-control d-inline-block w-auto f-i" v-model="selectedFromDate" required @keydown.delete.prevent :disabled="selectedRangeFilter !== 'Custom Date Range'" :max="selectedToDate">
                <input type="date" class="form-control d-inline-block w-auto f-i" v-model="selectedToDate" required @keydown.delete.prevent :disabled="selectedRangeFilter !== 'Custom Date Range'" :min="selectedFromDate">

                <button type="button" class="btn btn-primary btn-lg f-i" @click="fetchTimes(true)">Load</button>
            </div>

            <div>
                <button type="button" class="btn btn-primary btn-lg f-i" @click="exportTimes">Export Excel</button>
            </div>
        </h5>

        <table class="table table-bordered table-sm" ref="timesTable" data-cols-width="17,13,13,13,40">
            <thead>
                <tr>
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
                        <tr>
                            <td colspan="5" class="text-center" data-a-h="center" data-t="d" data-num-fmt="dd/mmmm/yyyy">{{ formatDate(date) }}</td>
                        </tr>
                        <tr v-for="time in timesDateWise">
                            <td class="text-center" data-a-h="center" data-t="d" data-num-fmt="dd/mmmm/yyyy">{{ formatDate(time.date) }}</td>
                            <td class="text-center" data-a-h="center">{{ formatTime(time.start_time) }}</td>
                            <td class="text-center" data-a-h="center">{{ formatTime(time.stop_time) }}</td>
                            <td class="text-center" data-a-h="center">{{ time.duration }}</td>
                            <td class="ws-pw" data-a-wrap="true">{{ time.work_report }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right" data-a-h="right">Total Duration</td>
                            <td colspan="1" class="text-center" data-a-h="center">{{ calculateTotalDuration(timesDateWise) }}</td>
                            <td colspan="1">{{ timesDateWise.length }} entr<template v-if="timesDateWise.length > 1">ies</template><template v-else>y</template></td>
                        </tr>
                    </template>
                </template>
                <tr v-else>
                    <td colspan="5" class="text-center" data-a-h="center">No records found</td>
                </tr>
            </tbody>
        </table>

        <confirm-modal
            v-if="stopTrackingConfirmModalShow"
            :message="stopTrackingMessage"
            confirmLabel="Save"
            @confirm="stopTrackingComplete"
            @cancel="stopTrackingConfirmModalShow = false; stopTime = null;"
        >
            <form ref="workReportForm">
                <textarea class="w-100p" v-model="workReport" style="height: 10em" v-focus required></textarea>
                <!-- <rich-text class="mb-3"></rich-text> -->
            </form>
        </confirm-modal>
    </div>
</template>

<script>
import format from 'date-fns/format'
import formatISO from 'date-fns/formatISO'
import parseISO from 'date-fns/parseISO'
import dateUtils from './../libs/dateUtils'
import differenceInSeconds from 'date-fns/differenceInSeconds'
import { startOfWeek, endOfWeek, subWeeks } from 'date-fns'
import TableToExcel from "@linways/table-to-excel"

export default {
    data() {
        return {
            tracking: false,
            today: formatISO(new Date()),
            currentTimeId: null,
            startTime: null,
            stopTime: null,
            workReport: '',
            times: [],
            stopTrackingConfirmModalShow: false,
            activeTrackLoaded: false,
            setIntervalTimer: null,
            elapsedTime: null,
            selectedRangeFilter: 'This Week',
            selectedFromDate: formatISO(startOfWeek(new Date(), { weekStartsOn: 1 }), { representation: 'date' }),
            selectedToDate: formatISO(endOfWeek(new Date(), { weekStartsOn: 1 }), { representation: 'date' })
        }
    },
    computed: {
        stopTrackingMessage() {
            return 'Enter work report for ' + this.formatDate(this.startTime) + ' from ' + this.formatTime(this.startTime) + ' - ' + this.formatTime(this.stopTime) + ' - ' + this.calculateDuration(this.startTime, this.stopTime)
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
        }
    },
    methods: {
        startTracking() {
            // refresh this.today - no real connection to the method at hand
            // I'm just using it as a refresh trigger, in case this.today is outdated
            this.today = formatISO(new Date())

            this.startTime = formatISO(new Date())

            let loader = this.$loading.show()

            axios.post('/time-tracker/start', {
                start_time: this.startTime
            }).then(response => {
                this.tracking = true
                this.currentTimeId = response.data
                loader.hide()
                this.$snotify.success('Started tracking')
                this.fetchTimes()
            })
        },
        stopTrackingBegin() {
            this.stopTime = formatISO(new Date())
            this.stopTrackingConfirmModalShow = true
        },
        stopTrackingComplete() {
            if(!this.$refs.workReportForm.reportValidity()) {
                return
            }

            let loader = this.$loading.show()

            axios.put(`/time-tracker/stop/${this.currentTimeId}`, {
                stop_time: this.stopTime,
                work_report: this.workReport
            }).then(() => {
                this.tracking = false
                this.stopTrackingConfirmModalShow = false
                this.currentTimeId = null
                this.startTime = null
                this.stopTime = null
                this.workReport = ''

                loader.hide()

                this.$snotify.success('Stopped tracking')

                this.fetchTimes()
            })
        },
        formatDate(dateObject) {
            return format(parseISO(dateObject), 'dd/MMMM/yyyy')
        },
        formatTime(dateObject) {
            return dateObject ? format(parseISO(dateObject), 'hh:mm a') : null
        },
        calculateDuration(from, to) {
            if(from && to) {
                return dateUtils.secondsInHHMMSS(differenceInSeconds(parseISO(to), parseISO(from)))
            } else {
                return null
            }
        },
        fetchTimes(showLoader=false) {
            let loader = null

            if(showLoader) {
                loader = this.$loading.show()
            }

            axios.get(`/time-tracker/times?from=${this.selectedFromDate}&to=${this.selectedToDate}`).then(response => {
                this.times = response.data

                if(loader) {
                    loader.hide()
                }
            })
        },
        fetchActiveTrack() {
            axios.get('/time-tracker/active-track').then(response => {
                if(response.data) {
                    this.tracking = true
                    this.currentTimeId = response.data.id
                    this.startTime = response.data.start_time
                }
                Vue.nextTick(() => {
                    this.activeTrackLoaded = true
                })
            })
        },
        refreshElapsedTime() {
            if(this.startTime) {
                if(this.stopTime === null) {
                    this.elapsedTime = this.calculateDuration(this.startTime, formatISO(new Date()))
                } else {
                    this.elapsedTime = this.calculateDuration(this.startTime, this.stopTime)
                }
            } else {
                this.elapsedTime = null
            }
        },
        calculateTotalDuration(times) {
            let totalDurationInSeconds = times.reduce((acc, currentItem) => acc + Number(currentItem.duration_in_seconds), 0)

            return dateUtils.secondsInHHMMSS(totalDurationInSeconds)
        },
        exportTimes() {
            TableToExcel.convert(this.$refs.timesTable, {
                name: `Timesheet for ${document.querySelector('#navbarDropdown').innerText.trim()} from ${this.formatDate(this.selectedFromDate)} to ${this.formatDate(this.selectedToDate)}.xlsx`
            })
        }
    },
    created() {
        this.fetchTimes()
        this.fetchActiveTrack()

        this.setIntervalTimer = setInterval(this.refreshElapsedTime, 500)
    },
    beforeDestroy() {
        clearInterval(this.setIntervalTimer)
    }
}
</script>
