#!/bin/bash

DATA_DIR="/app/data"

if [ -d "$DATA_DIR" ]; then
    rm -rf "$DATA_DIR"/*
    echo "Data directory cleared."
else
    echo "Data directory does not exist."
fi
