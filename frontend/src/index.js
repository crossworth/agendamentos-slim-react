import ReactDOM from 'react-dom'

import { publicURL } from './env'
process.env.PUBLIC_URL = publicURL

import { createAppComponent } from './components/App'
import './style.css'

ReactDOM.render(createAppComponent, document.querySelector('#root'))
