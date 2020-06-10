<template>
    <div>
        <h4>{{ formatDate(today) }} (Today) <template v-if="tracking">- {{ formatTime(startTime) }} - <span>{{ elapsedTime }}</span></template></h4>

        <template v-if="!tracking">
            <form @submit.prevent="startTracking">
                <input type="time" min="07:00" :max="currentTime" :value="selectedStartTime" required ref="selectedStartTimeInput" class="form-control d-inline-block w-auto" :disabled="!activeTrackLoaded">
                <button class="btn btn-primary" :disabled="!activeTrackLoaded">Start Tracking</button>
            </form>
        </template>
        <button class="btn btn-primary" @click="stopTrackingBegin" v-else>Stop Tracking</button>

        <time-tracker-table class="mt-5" ref="timeTable" :fetch-times-on-created="true"></time-tracker-table>

        <confirm-modal
            v-if="stopTrackingConfirmModalShow"
            confirmLabel="Save"
            @confirm="stopTrackingComplete"
            @cancel="stopTrackingConfirmModalShow = false; stopTime = null;"
        >
            <form ref="workReportForm">
                <p style="font-size: 1.2em" class="mt-3">
                    <span style="color: red" v-if="formatDate(startTime) !== formatDate(stopTime)">You seem to have forgotten to stop tracking on {{ formatDate(startTime) }}, so you'll have to manually specify a stop time</span>
                    <br>
                    Enter work report for {{ formatDate(startTime) }} from {{ formatTime(startTime) }} -
                    <span v-if="formatDate(startTime) === formatDate(stopTime)">
                        {{ formatTime(stopTime) }}
                    </span>
                    <span v-else>
                        <input type="time" :min="format(parseISO(startTime), 'HH:mm')" class="form-control w-auto d-inline-block" required ref="stopTimeOverrideTimeInput">
                    </span>
                    <span v-if="formatDate(startTime) === formatDate(stopTime)"> - {{ calculateDuration(startTime, stopTime) }}</span>
                </p>
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
import set from 'date-fns/set'
import differenceInSeconds from 'date-fns/differenceInSeconds'
import dateUtils from './../libs/dateUtils'
import dateUtilsMixin from './../mixins/dateUtilsMixin'

export default {
    mixins: [
        dateUtilsMixin
    ],
    data() {
        return {
            tracking: false,
            today: formatISO(new Date()),
            currentTimeId: null,
            startTime: null,
            stopTime: null,
            workReport: '',
            stopTrackingConfirmModalShow: false,
            activeTrackLoaded: false,
            setIntervalTimer: null,
            elapsedTime: null,
            selectedStartTime: format(new Date(), 'HH:mm'),
            currentTime: format(new Date(), 'HH:mm')
        }
    },
    methods: {
        startTracking() {
            // refresh this.today - no real connection to the method at hand
            // I'm just using it as a refresh trigger, in case this.today is outdated
            this.today = formatISO(new Date())

            this.selectedStartTime = this.$refs.selectedStartTimeInput.value
            const selectedStartTimeAsDateObj = this.$refs.selectedStartTimeInput.valueAsDate
            const startDateTime = set(new Date(), { hours: selectedStartTimeAsDateObj.getUTCHours(), minutes: selectedStartTimeAsDateObj.getUTCMinutes() })
            this.startTime = formatISO(startDateTime)

            let loader = this.$loading.show()

            axios.post('/time-tracker/start', {
                start_time: this.startTime
            }).then(response => {
                this.tracking = true
                this.currentTimeId = response.data
                loader.hide()
                this.$snotify.success('Started tracking')
                this.$refs.timeTable.fetchTimes()
            }).catch(error => {
                this.startTime = null
                this.$snotify.error(error.response.data)
                loader.hide()
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

            if(this.formatDate(this.startTime) !== this.formatDate(this.stopTime)) {
                const stopTimeOverrideAsDateObj = this.$refs.stopTimeOverrideTimeInput.valueAsDate

                this.stopTime = set(parseISO(this.startTime), {
                    hours: stopTimeOverrideAsDateObj.getUTCHours(),
                    minutes: stopTimeOverrideAsDateObj.getUTCMinutes(),
                    seconds: '00'
                })

                this.stopTime = formatISO(new Date())
            }

            axios.put(`/time-tracker/stop/${this.currentTimeId}`, {
                stop_time: this.stopTime,
                work_report: this.workReport
            }).then(() => {
                // reset selectedStartTime on stop traking
                this.selectedStartTime = this.currentTime

                this.tracking = false

                this.stopTrackingConfirmModalShow = false
                this.currentTimeId = null
                this.startTime = null
                this.stopTime = null
                this.workReport = ''

                loader.hide()

                this.$snotify.success('Stopped tracking')

                this.$refs.timeTable.fetchTimes()
            })
        },
        calculateDuration(from, to) {
            if(from && to) {
                return dateUtils.secondsInHHMMSS(differenceInSeconds(parseISO(to), parseISO(from)))
            } else {
                return null
            }
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

            this.currentTime = format(new Date(), 'HH:mm')
        },
        format,
        parseISO
    },
    created() {
        this.fetchActiveTrack()

        this.setIntervalTimer = setInterval(this.refreshElapsedTime, 500)
    },
    beforeDestroy() {
        clearInterval(this.setIntervalTimer)
    }
}
</script>
