// +build postgres_tests

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package postgres

import (
	"fmt"
	"testing"
)

func TestPlugin_databasesBloatingHandler(t *testing.T) {
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}

	type args struct {
		conn   *postgresConn
		params []string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		wantErr bool
	}{
		{
			fmt.Sprintf("databasesBloatingHandler should return size of bloating tables for each database "),
			&impl,
			args{conn: sharedPool, params: []string{"postgres"}},
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {

			_, err := tt.p.databasesBloatingHandler(tt.args.conn, keyPostgresDatabasesBloating, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.databaseBloatingHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			/* if got.(int64) == 0 {
				t.Errorf("Plugin.databasesHandler() = %v", got)
			} */
		})
	}
}
