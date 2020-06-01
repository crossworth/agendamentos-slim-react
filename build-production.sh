#!/bin/bash

cp production.env.php env.php
cp production.env.js frontend/src/env.js

cd frontend/ && yarn && PUBLIC_URL="/agenda" yarn build || return

