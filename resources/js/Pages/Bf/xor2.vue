<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const dataArray1 = [ 5, 10, 15, 20, 25, 30, 35, 40, 45, 50,
                    55, 60, 65, 70, 75, 80, 85, 90, 95, 100 ];

const dataArray2 = [ 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                    0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ];
let arrayBack = [];

let arrayin = [ 5, 10, 15, 20, 25, 30 ];
let arrayOut = [];

let a = 0
let b = 0
let c = 0
let a2 = 0
let b2 = 0
let c2 = 0


let thisVal = 0;
let lastVal = 0;

let count = 0;
let active = 'a';
let lastActive = '';

function encode() {
    console.log('encoding');
    for ( count = 0; count < 6; count++ ) {
        active = encode_vals( active , count );
    }
    lastActive = active;
}

function encode_vals( active, count ) {
    let returnVal = '';
    if( active === 'a' ) {          // a is active, b is previous, c is anchor
        a = c ^ ( arrayin[ count ] ^ count );
        b = b ^ c;
        returnVal = 'b';
    } 
    else if( active === 'b' ) {     // b is active, c is previous, a is anchor
        b = a ^ ( arrayin[ count ] ^ count );
        c= c ^ a;
        returnVal = 'c';
    } 
    else if( active === 'c' ) {     // c is active, a is previous, b is anchor
        c = b ^ ( arrayin[ count ] ^ count );
        a = a ^ b; 
        returnVal = 'a';
    }
    return returnVal
}

function decode() {
    console.log('decoding');
    a2 = a;
    b2 = b;
    c2 = c;

    if( lastActive === 'a' ) active = 'c';      // lastActive - 1
    else if( lastActive === 'b' ) active = 'a';    // lastActive - 1
    else if( lastActive === 'c' ) active = 'b';    // lastActive - 1

    for ( count = 5; count >= 0; count-- ) {
        arrayOut.push( decode_vals( active , count ) );
    }
    console.log('This is it');
    console.log('Out:' + arrayOut[1]);
    console.log(arrayOut[0]);
    console.log(arrayOut[1]);
    console.log(arrayOut[2]);
    console.log(arrayOut[3]);
    console.log(arrayOut[4]);
    console.log(arrayOut[5]);
}

function decode_vals( active, count ) {
    if( active === 'a' ) {
        thisVal = (a2 ^ c2) ^ count;
        lastVal = b2 ^ c2;
        a2 = thisVal;
        b2 = lastVal;
    }
    else if( active === 'b' ) {
        thisVal = (b2 ^ a2) ^ count;
        lastVal = c2 ^ a2;
        b2 = thisVal;
        c2 = lastVal;
    }
    else if( active === 'c' ) {
        thisVal = (c2 ^ b2) ^ count;
        lastVal = a2 ^ b2;
        c2 = thisVal;
        a2 = lastVal;
    }

    return thisVal;
}

</script>

<template>
    <Head title="Xor2" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Xor2
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-xs sm:rounded-lg"
                >
                    <div class="p-6 text-gray-900">
                        <button type = "button" class="btn btn-sm btn-primary mr-4" @click="encode()">Encode</button>
                        <button type = "button" class="btn btn-sm btn-secondary" @click="decode()">Decode</button>                                
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
