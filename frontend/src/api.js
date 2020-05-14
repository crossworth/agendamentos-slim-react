import axios from 'axios'
import { message } from 'antd'

const api = axios.create({
  baseURL: 'http://agenda-slim.test/api',
})

api.interceptors.response.use((response) => {
  return response
}, (error) => {

  if (error.response.status === 401) {
    alert('Você deve fazer login')
    return
  }

  let errorMessage = error

  if (error.response &&
    error.response.data &&
    error.response.data.message) {
    errorMessage = error.response.data.message
  }

  message.error('Ops, ocorreu um erro: ' + errorMessage)

  return Promise.reject(error)
})

const createAppointment = appointment => {
  return api.post('/appointments', {
    name: appointment.name,
    address: appointment.address,
    landline_phone_number: appointment.landline_phone_number,
    mobile_phone_number: appointment.mobile_phone_number,
    email: appointment.email,
    number_of_employees: appointment.number_of_employees,
    date: appointment.date,
    return_date: appointment.return_date,
    observations: appointment.observations,
    documents: appointment.documents,
  })
}

const getAppointment = id => {
  return api.get('/appointments/' + id)
}

const getAppointments = () => {
  return api.get('/appointments')
}

const searchAppointments = (date, returnDate) => {
  return api.get('/appointments', {
    params: {
      date: date ? date.format() : null,
      return_date: returnDate ? returnDate.format() : null,
    }
  })
}

const updateAppointment = appointment => {
  return api.put(`/appointments/${appointment.id}`, {
    name: appointment.name,
    address: appointment.address,
    landline_phone_number: appointment.landline_phone_number,
    mobile_phone_number: appointment.mobile_phone_number,
    email: appointment.email,
    number_of_employees: appointment.number_of_employees,
    date: appointment.date,
    return_date: appointment.return_date,
    observations: appointment.observations
  })
}

export { createAppointment, updateAppointment, getAppointment, getAppointments, searchAppointments }
