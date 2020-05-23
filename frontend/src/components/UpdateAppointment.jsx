import React from 'react'

import { withRouter } from 'react-router-dom'
import moment from 'moment'
import { Typography, Form, Input, Button, Select, InputNumber, DatePicker, message, Alert, Spin } from 'antd'
import { getAppointment, updateAppointment } from '../api'
import { dddSelector, phoneValidator, sanitizePhoneNumber } from './CreateAppointment'

const { Title } = Typography

const layout = {
  labelCol: { span: 24 },
  wrapperCol: { span: 24 },
}

const dddFromNumber = number => {
  if (!number || number.length < 2) {
    return 44
  }

  return number.replace(/\D/g, '').substr(0, 2)
}

const numberFromPhone = number => {
  if (!number || number.length < 2) {
    return ''
  }

  return number.replace(/\D/g, '').substr(2)
}

const getDate = date => {
  if (!date) {
    return ''
  }

  return moment(date)
}

class UpdateAppointment extends React.Component {

  constructor(props, context, state) {
    super(props, context)
    this.state = {
      loading: true,
      error: false,
      message: '',
      appointment: {},
      initialValue: {},
    }

    this.id = this.props.match.params.id
    this.onSubmit = this.onSubmit.bind(this)
  }

  componentDidMount() {
    getAppointment(this.id).then(result => {
      this.setState({
        loading: false,
        appointment: result.data,
        initialValue: {
          ddd_landline_phone_number: dddFromNumber(result.data.landline_phone_number),
          ddd_mobile_phone_number: dddFromNumber(result.data.mobile_phone_number),
          landline_phone_number: numberFromPhone(result.data.landline_phone_number),
          mobile_phone_number: numberFromPhone(result.data.mobile_phone_number),
          name: result.data.name,
          address: result.data.address,
          email: result.data.email,
          number_of_employees: result.data.number_of_employees,
          date: getDate(result.data.date),
          return_date: getDate(result.data.return_date),
          observations: result.data.observations,
        }
      })

    }).catch(error => {
      this.setState({
        loading: false,
        error: true,
        message: error.response.data.message,
      })
    })
  }

  onSubmit(values) {
    updateAppointment({
      id: this.state.appointment.id,
      name: values.name,
      address: values.address,
      landline_phone_number: sanitizePhoneNumber(values.ddd_landline_phone_number, values.landline_phone_number),
      mobile_phone_number: sanitizePhoneNumber(values.ddd_mobile_phone_number, values.mobile_phone_number),
      email: values.email,
      number_of_employees: values.number_of_employees,
      date: values.date ? values.date.format() : null,
      return_date: values.return_date ? values.return_date.format() : null,
      observations: values.observations
    }).then(result => {
      message.success('Agendamento atualizado com sucesso')
      this.props.history.push(`${process.env.PUBLIC_URL}/agendamentos/${result.data.id}`)
    })
  }

  renderForm() {
    return <div>
      <Title level={3}>Atualizar agendamento</Title>
      <Form
        {...layout}
        name="basic"
        layout="vertical"
        initialValues={this.state.initialValue}
        onFinish={this.onSubmit}>

        <Form.Item
          label="Nome"
          name="name"
          rules={[{ required: true, message: 'Informe o nome' }]}>
          <Input/>
        </Form.Item>

        <Form.Item
          label="Endereço"
          name="address">
          <Input/>
        </Form.Item>

        <Form.Item
          label="Telefone fixo"
          name="landline_phone_number"
          rules={[() => ({
            validator: phoneValidator
          })]}>
          <Input addonBefore={dddSelector('landline_phone_number')}/>
        </Form.Item>

        <Form.Item
          label="Telefone móvel"
          name="mobile_phone_number"
          rules={[
            { required: true, message: 'Informe o telefone móvel' },
            () => ({
              validator: phoneValidator
            })]}>
          <Input addonBefore={dddSelector('mobile_phone_number')}/>
        </Form.Item>

        <Form.Item
          label="Email"
          name="email"
          rules={[
            { required: true, message: 'Informe o email' },
            { type: 'email', message: 'Informe um email válido' }
          ]}>
          <Input/>
        </Form.Item>

        <Form.Item
          label="Número de colaboradores"
          name="number_of_employees">
          <InputNumber min={0}/>
        </Form.Item>

        <Form.Item
          label="Data (visita)"
          name="date">
          <DatePicker format="DD/MM/YYYY"/>
        </Form.Item>

        <Form.Item
          label="Data de retorno"
          name="return_date">
          <DatePicker format="DD/MM/YYYY"/>
        </Form.Item>

        <Form.Item
          label="Observações"
          name="observations">
          <Input.TextArea/>
        </Form.Item>

        <Form.Item>
          <Button type="primary" htmlType="submit">
            Atualizar
          </Button>
        </Form.Item>
      </Form>
    </div>
  }

  render() {
    let content
    if (this.state.error) {
      content = <div>
        <Alert
          message="Ocorreu um erro"
          description={this.state.message}
          type="error"
          showIcon
        />
      </div>
    } else if (this.state.loading) {
      content = <div>
        ...
      </div>
    } else {
      content = this.renderForm()
    }

    return <div>
      <Spin tip="Carregando..." spinning={this.state.loading}>
        {content}
      </Spin>
    </div>
  }
}

export default withRouter(UpdateAppointment)
