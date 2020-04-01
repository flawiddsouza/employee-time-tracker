import format from 'date-fns/format'
import parseISO from 'date-fns/parseISO'

export default {
    methods: {
        formatDate(dateObject) {
            return format(parseISO(dateObject), 'dd/MMMM/yyyy')
        },
        formatTime(dateObject) {
            return dateObject ? format(parseISO(dateObject), 'hh:mm a') : null
        }
    }
}
