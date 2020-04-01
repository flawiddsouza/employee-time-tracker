<template>
    <div>
        <h4>{{ formatDate(today) }} (Today) <template v-if="tracking">- {{ formatTime(startTime) }} - <span>{{ elapsedTime }}</span></template></h4>

        <button class="btn btn-primary" @click="startTracking" v-if="!tracking" :disabled="!activeTrackLoaded">Start Tracking</button>
        <button class="btn btn-primary" @click="stopTrackingBegin" v-else>Stop Tracking</button>

        <time-tracker-table class="mt-5" ref="timeTable" :fetch-times-on-created="true"></time-tracker-table>

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
import formatISO from 'date-fns/formatISO'
import parseISO from 'date-fns/parseISO'
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
                this.$refs.timeTable.fetchTimes()
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
        }
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
