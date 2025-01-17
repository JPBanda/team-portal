import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';

import { AantalGespeeldePunten } from './grafieken/aantal-gespeelde-punten';
import { Chart } from 'chart.js';
import { GetGrafiekPuntenPerRotatie } from './grafieken/punten-per-rotatie';
import { IPunten } from './models/IPunten';
import { SetPlusminusGraph } from './grafieken/plusminus-per-persoon';
import { SetServicesGraph } from './grafieken/services-per-persoon';
import { StatistiekService } from '../../core/services/statistieken.service';

@Component({
  selector: 'teamportal-statistieken',
  templateUrl: './statistieken.component.html',
  styleUrls: ['./statistieken.component.scss'],
})
export class StatistiekenComponent implements OnInit {
  statistiekForm: FormGroup;
  statistieken: any;

  rotatieGraph: Chart;
  servicesGraph: Chart;
  plusminusGraph: Chart;
  gespeeldePuntenGraph: Chart;

  wedstrijden: any[];
  errorMessage: string;
  isLoading = true;

  constructor(
    private fb: FormBuilder,
    private statistiekService: StatistiekService
  ) {}

  ngOnInit() {
    this.statistiekForm = this.fb.group({
      geselecteerdeWedstrijd: '',
      spelsysteem: null,
      rotatiekeuze: 'puntenPerRotatie',
      service: 'totaal',
      plusminusType: 'totaal',
    });

    this.statistiekForm
      .get('spelsysteem')
      .valueChanges.subscribe(() => this.DisplayRotatieStats());

    this.statistiekForm
      .get('rotatiekeuze')
      .valueChanges.subscribe(() => this.DisplayRotatieStats());

    this.statistiekForm
      .get('plusminusType')
      .valueChanges.subscribe(() => this.DisplayPlusminusStats());

    this.statistiekForm
      .get('geselecteerdeWedstrijd')
      .valueChanges.subscribe(() => {
        this.LoadStatistieken();
      });

    this.statistiekService
      .GetEigenWedstrijden()
      .subscribe((wedstrijden) => (this.wedstrijden = wedstrijden));

    this.LoadStatistieken();
  }

  LoadStatistieken() {
    this.isLoading = true;
    const matchId = this.statistiekForm.get('geselecteerdeWedstrijd').value;
    this.statistiekService.GetStatistieken(matchId).subscribe(
      (statistieken) => {
        this.statistieken = statistieken;
        const spelsysteem =
          statistieken.spelsystemen.length > 0
            ? statistieken.spelsystemen[0].type
            : null;
        this.statistiekForm.get('spelsysteem').setValue(spelsysteem);
        this.isLoading = false;

        this.DisplayStatistieken();
      },
      (error) => {
        this.errorMessage = error.error.message;
        this.isLoading = false;
      }
    );
  }

  DisplayStatistieken() {
    this.DisplayGespeeldePunten();
    this.DisplayRotatieStats();
    this.DisplayServicesStats();
    this.DisplayPlusminusStats();
  }

  DisplayGespeeldePunten() {
    if (this.gespeeldePuntenGraph) {
      this.gespeeldePuntenGraph.destroy();
    }
    this.gespeeldePuntenGraph = AantalGespeeldePunten(
      this.statistieken.gespeeldePunten
    );
  }

  DisplayPlusminusStats() {
    const type = this.statistiekForm.get('plusminusType').value;
    let label: string;
    let plusminus: IPunten[];
    switch (type) {
      case 'totaal':
        plusminus = this.statistieken.plusminus;
        label = `Totaal`;
        break;
      case 'voor':
        plusminus = this.statistieken.plusminusAlleenVoor;
        label = `Voor`;
        break;
    }

    if (this.plusminusGraph) {
      this.plusminusGraph.destroy();
    }
    this.plusminusGraph = SetPlusminusGraph(plusminus, label);
  }

  DisplayServicesStats() {
    if (this.servicesGraph) {
      this.servicesGraph.destroy();
    }
    this.servicesGraph = SetServicesGraph(this.statistieken.services);
  }

  DisplayRotatieStats() {
    if (this.rotatieGraph) {
      this.rotatieGraph.destroy();
    }

    const rotatiekeuze = this.statistiekForm.get('rotatiekeuze').value;
    const type = this.statistiekForm.get('spelsysteem').value;
    const i = this.statistieken.spelsystemen.findIndex(
      (spelsysteem) => spelsysteem.type === type
    );

    if (i === -1) {
      return;
    }

    const totaalAantalPunten =
      this.statistieken.spelsystemen[i].totaalAantalPunten;

    const label = `Winstpercentage (${totaalAantalPunten} punten)`;
    let puntenPerRotatie: IPunten[];
    switch (rotatiekeuze) {
      case 'puntenPerRotatie':
        puntenPerRotatie = this.statistieken.spelsystemen[i].puntenPerRotatie;
        break;
      case 'puntenPerRotatieEigenService':
        puntenPerRotatie =
          this.statistieken.spelsystemen[i].puntenPerRotatieEigenService;
        break;
      case 'puntenPerRotatieServiceontvangst':
        puntenPerRotatie =
          this.statistieken.spelsystemen[i].puntenPerRotatieServiceontvangst;
        break;
    }

    this.rotatieGraph = GetGrafiekPuntenPerRotatie(puntenPerRotatie, label);
  }

  GetReeksText(reeks: any) {
    const naam = reeks.naam ? reeks.naam : `Rugnummer ${reeks.rugnummer}`;
    return `${naam} (${reeks.skcTeam} - ${reeks.otherTeam}, ${reeks.set}e set)`;
  }
}
