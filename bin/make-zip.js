#!/usr/bin/env node
/**
 * zipコマンドが無い環境（Windows の Git Bash など）向けの、依存なしの簡易zip作成ツール。
 * 使い方: node bin/make-zip.js <出力zip> <ベースディレクトリ> <ベース直下のルートフォルダ名>
 *   例:   node bin/make-zip.js dist/lp-service-v2.zip dist lp-service-v2
 * ベースディレクトリ内の <ルートフォルダ名>/ 以下を、エントリ名を「ルートフォルダ名/相対パス」（スラッシュ区切り）にしてzip化する。
 * エントリ名は常にフォワードスラッシュ（PowerShell 5 の Compress-Archive のようなバックスラッシュ問題を避ける）。
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const zlib = require( 'zlib' );

const [ , , outFile, baseDir, rootName ] = process.argv;
if ( ! outFile || ! baseDir || ! rootName ) {
	console.error( '使い方: node bin/make-zip.js <出力zip> <ベースディレクトリ> <ルートフォルダ名>' );
	process.exit( 2 );
}

// CRC32
const CRC_TABLE = ( () => {
	const t = new Uint32Array( 256 );
	for ( let n = 0; n < 256; n++ ) {
		let c = n;
		for ( let k = 0; k < 8; k++ ) {
			c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
		}
		t[ n ] = c >>> 0;
	}
	return t;
} )();
function crc32( buf ) {
	let c = 0xffffffff;
	for ( let i = 0; i < buf.length; i++ ) {
		c = CRC_TABLE[ ( c ^ buf[ i ] ) & 0xff ] ^ ( c >>> 8 );
	}
	return ( c ^ 0xffffffff ) >>> 0;
}

function dosDateTime( date ) {
	const year = Math.max( date.getFullYear(), 1980 );
	const time = ( date.getHours() << 11 ) | ( date.getMinutes() << 5 ) | ( date.getSeconds() >> 1 );
	const day = ( ( year - 1980 ) << 9 ) | ( ( date.getMonth() + 1 ) << 5 ) | date.getDate();
	return { time, day };
}

// ファイルを再帰的に列挙（ディレクトリ自体のエントリは作らない。ファイルだけで十分）
function walk( dir, rel, out ) {
	const names = fs.readdirSync( dir ).sort();
	for ( const name of names ) {
		const full = path.join( dir, name );
		const r = rel + '/' + name;
		const st = fs.statSync( full );
		if ( st.isDirectory() ) {
			walk( full, r, out );
		} else if ( st.isFile() ) {
			out.push( { full, name: r, mtime: st.mtime } );
		}
	}
}

const rootDir = path.join( baseDir, rootName );
if ( ! fs.existsSync( rootDir ) ) {
	console.error( 'ルートフォルダが見つかりません: ' + rootDir );
	process.exit( 1 );
}
const files = [];
walk( rootDir, rootName, files );

const parts = [];
const central = [];
let offset = 0;

for ( const f of files ) {
	const data = fs.readFileSync( f.full );
	const nameBuf = Buffer.from( f.name, 'utf8' );
	const crc = crc32( data );
	let method = 8;
	let body = zlib.deflateRawSync( data, { level: 9 } );
	if ( body.length >= data.length ) {
		method = 0;
		body = data;
	}
	const { time, day } = dosDateTime( f.mtime );

	const local = Buffer.alloc( 30 );
	local.writeUInt32LE( 0x04034b50, 0 );
	local.writeUInt16LE( 20, 4 ); // 展開に必要なバージョン
	local.writeUInt16LE( 0x0800, 6 ); // UTF-8 ファイル名
	local.writeUInt16LE( method, 8 );
	local.writeUInt16LE( time, 10 );
	local.writeUInt16LE( day, 12 );
	local.writeUInt32LE( crc, 14 );
	local.writeUInt32LE( body.length, 18 );
	local.writeUInt32LE( data.length, 22 );
	local.writeUInt16LE( nameBuf.length, 26 );
	local.writeUInt16LE( 0, 28 );
	parts.push( local, nameBuf, body );

	const cen = Buffer.alloc( 46 );
	cen.writeUInt32LE( 0x02014b50, 0 );
	cen.writeUInt16LE( ( 3 << 8 ) | 20, 4 ); // 作成元: Unix
	cen.writeUInt16LE( 20, 6 );
	cen.writeUInt16LE( 0x0800, 8 );
	cen.writeUInt16LE( method, 10 );
	cen.writeUInt16LE( time, 12 );
	cen.writeUInt16LE( day, 14 );
	cen.writeUInt32LE( crc, 16 );
	cen.writeUInt32LE( body.length, 20 );
	cen.writeUInt32LE( data.length, 24 );
	cen.writeUInt16LE( nameBuf.length, 28 );
	cen.writeUInt16LE( 0, 30 );
	cen.writeUInt16LE( 0, 32 );
	cen.writeUInt16LE( 0, 34 );
	cen.writeUInt16LE( 0, 36 );
	cen.writeUInt32LE( ( ( 0o100644 << 16 ) >>> 0 ), 38 ); // 通常ファイル 0644
	cen.writeUInt32LE( offset, 42 );
	central.push( cen, nameBuf );

	offset += local.length + nameBuf.length + body.length;
}

const centralBuf = Buffer.concat( central );
const end = Buffer.alloc( 22 );
end.writeUInt32LE( 0x06054b50, 0 );
end.writeUInt16LE( 0, 4 );
end.writeUInt16LE( 0, 6 );
end.writeUInt16LE( files.length, 8 );
end.writeUInt16LE( files.length, 10 );
end.writeUInt32LE( centralBuf.length, 12 );
end.writeUInt32LE( offset, 16 );
end.writeUInt16LE( 0, 20 );

fs.mkdirSync( path.dirname( outFile ), { recursive: true } );
fs.writeFileSync( outFile, Buffer.concat( [ ...parts, centralBuf, end ] ) );
console.log( files.length + ' ファイルを ' + outFile + ' に書き出しました（node による簡易zip）' );
