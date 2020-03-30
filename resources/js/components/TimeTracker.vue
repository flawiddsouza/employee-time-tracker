<template>
    <div>
        <h4>{{ formatDate(today) }} (Today) <template v-if="tracking">- {{ formatTime(startTime) }} - {{ elapsedTime }}</template></h4>

        <button class="btn btn-primary" @click="startTracking" v-if="!tracking" :disabled="!activeTrackLoaded">Start Tracking</button>
        <button class="btn btn-primary" @click="stopTrackingBegin" v-else>Stop Tracking</button>

        <h4 class="mt-5">This Week</h4>

        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th style="width: 9em" class="text-center">Date</th>
                    <th style="width: 7em" class="text-center">Start Time</th>
                    <th style="width: 7em" class="text-center">End Time</th>
                    <th style="width: 7em" class="text-center">Duration</th>
                    <th>Work Report</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="Object.keys(times).length > 0">
                    <template v-for="(timesDateWise, date) in times">
                        <tr>
                            <td colspan="100%" class="text-center">{{ formatDate(date) }}</td>
                        </tr>
                        <tr v-for="time in timesDateWise">
                            <td class="text-center">{{ formatDate(time.date) }}</td>
                            <td class="text-center">{{ formatTime(time.start_time) }}</td>
                            <td class="text-center">{{ formatTime(time.stop_time) }}</td>
                            <td class="text-center">{{ time.duration }}</td>
                            <td class="ws-pw">{{ time.work_report }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right">Total Duration</td>
                            <td colspan="1" class="text-center">{{ calculateTotalDuration(timesDateWise) }}</td>
                            <td colspan="100%">{{ timesDateWise.length }} entr<template v-if="timesDateWise.length > 1">ies</template><template v-else>y</template></td>
                        </tr>
                    </template>
                </template>
                <tr class="text-center" v-else>
                    <td colspan="100%">No records found</td>
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
                <textarea class="input w-100p" v-model="workReport" style="height: 10em" v-focus required></textarea>
            </form>
        </confirm-modal>
    </div>
</template>

<script>
import ConfirmModal from './ConfirmModal'
import format from 'date-fns/format'
import formatISO from 'date-fns/formatISO'
import parseISO from 'date-fns/parseISO'
import dateUtils from './../libs/dateUtils'
import differenceInSeconds from 'date-fns/differenceInSeconds'

export default {
    components: {
        ConfirmModal
    },
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
            elapsedTime: null
        }
    },
    computed: {
        stopTrackingMessage() {
            return 'Enter work report for ' + this.formatDate(this.startTime) + ' from ' + this.formatTime(this.startTime) + ' - ' + this.formatTime(this.stopTime) + ' - ' + this.calculateDuration(this.startTime, this.stopTime)
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
        fetchTimes() {
            axios.get('/time-tracker/times').then(response => {
                this.times = response.data
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
