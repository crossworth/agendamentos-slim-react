import ReactDOM from 'react-dom'
import { publicURL } from './env'
import { createAppComponent } from './components/App'
import './style.css'

process.env.PUBLIC_URL = publicURL

ReactDOM.render(createAppComponent, document.querySelector('#root'))
